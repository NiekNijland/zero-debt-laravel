<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Override;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use WeakMap;

final readonly class TypedReceivers
{
    /**
     * @param WeakMap<Node, true> $held the variable and property nodes that hold an object of a listed type
     * @param list<string> $containerKeys
     * @param list<string> $types
     */
    private function __construct(
        private array $types,
        private WeakMap $held,
        private array $containerKeys,
    ) {
    }

    /**
     * @param array<array-key, Node> $statements resolved statements
     * @param list<string> $types fully qualified type names, without a leading backslash
     * @param list<string> $containerKeys the strings that `app()` resolves to such an object (`'events'`, `'log'`, `'db'`)
     */
    public static function in(array $statements, array $types, array $containerKeys = []): self
    {
        $held = new WeakMap();
        $reader = new self($types, $held, $containerKeys);

        new NodeTraverser(new class($reader, $held, $types) extends NodeVisitorAbstract
        {
            /** @var list<list<string>> property names that hold the type, one entry per class on the way down */
            private array $properties = [];

            /** @var list<list<string>> variable names that hold the type, one entry per function on the way down */
            private array $variables = [[]];

            /**
             * @param WeakMap<Node, true> $held
             * @param list<string> $types
             */
            public function __construct(private readonly TypedReceivers $reader, private readonly WeakMap $held, private readonly array $types)
            {
            }

            #[Override]
            public function enterNode(Node $node): null
            {
                if ($node instanceof ClassLike) {
                    $this->properties[] = $this->propertiesOf($node);
                }

                if ($node instanceof FunctionLike) {
                    $this->variables[] = $this->variablesOf($node);
                }

                if ($node instanceof Assign && $node->var instanceof Variable && is_string($node->var->name)) {
                    $this->assign($node->var->name, $node->expr);
                }

                if (($node instanceof Variable || $node instanceof PropertyFetch) && $this->holds($node)) {
                    $this->held[$node] = true;
                }

                return null;
            }

            #[Override]
            public function leaveNode(Node $node): null
            {
                if ($node instanceof ClassLike) {
                    array_pop($this->properties);
                }

                if ($node instanceof FunctionLike) {
                    array_pop($this->variables);
                }

                return null;
            }

            private function holds(Expr $receiver): bool
            {
                return match (true) {
                    $receiver instanceof Variable => is_string($receiver->name) && in_array($receiver->name, $this->currentVariables(), true),
                    $receiver instanceof PropertyFetch => $receiver->var instanceof Variable && $receiver->var->name === 'this'
                        && $receiver->name instanceof Identifier && $this->properties !== []
                        && in_array($receiver->name->toString(), $this->currentProperties(), true),
                    default => $this->reader->holds($receiver),
                };
            }

            private function assign(string $name, Expr $value): void
            {
                $names = array_values(array_diff($this->currentVariables(), [$name]));

                if (($value instanceof Variable || $value instanceof PropertyFetch || $value instanceof FuncCall) && $this->holds($value)) {
                    $names[] = $name;
                }

                array_pop($this->variables);
                $this->variables[] = $names;
            }

            /**
             * @return list<string>
             */
            private function currentVariables(): array
            {
                $names = end($this->variables);

                return $names === false ? [] : $names;
            }

            /**
             * @return list<string>
             */
            private function currentProperties(): array
            {
                $names = end($this->properties);

                return $names === false ? [] : $names;
            }

            /**
             * @return list<string>
             */
            private function propertiesOf(ClassLike $class): array
            {
                $names = [];

                foreach ($class->stmts as $statement) {
                    if ($statement instanceof Property && TypedReceivers::typeHolds($statement->type, $this->types)) {
                        foreach ($statement->props as $item) {
                            $names[] = $item->name->toString();
                        }
                    }

                    if ($statement instanceof ClassMethod && $statement->name->toLowerString() === '__construct') {
                        foreach ($statement->params as $parameter) {
                            if ($parameter->flags !== 0 && TypedReceivers::typeHolds($parameter->type, $this->types) && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                                $names[] = $parameter->var->name;
                            }
                        }
                    }
                }

                return $names;
            }

            /**
             * @return list<string> the typed parameters, and what a closure takes in with `use` or an arrow function takes from outside
             */
            private function variablesOf(FunctionLike $function): array
            {
                $outer = $this->currentVariables();
                $names = $function instanceof ArrowFunction ? $outer : [];

                if ($function instanceof Closure) {
                    foreach ($function->uses as $use) {
                        if (is_string($use->var->name) && in_array($use->var->name, $outer, true)) {
                            $names[] = $use->var->name;
                        }
                    }
                }

                foreach ($function->getParams() as $parameter) {
                    if (TypedReceivers::typeHolds($parameter->type, $this->types) && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                        $names[] = $parameter->var->name;
                    }
                }

                return $names;
            }
        })->traverse($statements);

        return $reader;
    }

    /**
     * @param list<string> $types
     *
     * @internal for the visitor of this class
     */
    public static function typeHolds(?Node $type, array $types): bool
    {
        return match (true) {
            $type instanceof Name => in_array(mb_ltrim($type->toString(), '\\'), $types, true),
            $type instanceof NullableType => self::typeHolds($type->type, $types),
            $type instanceof UnionType, $type instanceof IntersectionType => array_any($type->types, static fn (Node $member): bool => self::typeHolds($member, $types)),
            default => false,
        };
    }

    public function holds(Expr $receiver): bool
    {
        return match (true) {
            $receiver instanceof Variable, $receiver instanceof PropertyFetch => isset($this->held[$receiver]),
            $receiver instanceof FuncCall => $receiver->name instanceof Name && $receiver->name->toLowerString() === 'app' && $this->resolvesFromContainer(($receiver->getArgs()[0] ?? null)?->value),
            default => false,
        };
    }

    private function resolvesFromContainer(?Expr $argument): bool
    {
        return match (true) {
            $argument instanceof String_ => in_array($argument->value, $this->containerKeys, true),
            $argument instanceof ClassConstFetch => $argument->class instanceof Name && $argument->name instanceof Identifier
                && $argument->name->toLowerString() === 'class' && in_array(mb_ltrim($argument->class->toString(), '\\'), $this->types, true),
            default => false,
        };
    }
}
