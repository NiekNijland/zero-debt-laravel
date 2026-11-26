<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;

final readonly class TypedReceivers
{
    /**
     * @param list<string> $variables
     * @param list<string> $properties
     * @param list<string> $containerKeys
     * @param list<string> $types
     */
    private function __construct(
        private array $types,
        private array $variables,
        private array $properties,
        private array $containerKeys,
    ) {
    }

    /**
     * @param array<array-key, Node> $statements
     * @param list<string> $types
     * @param list<string> $containerKeys
     */
    public static function in(array $statements, array $types, array $containerKeys = []): self
    {
        $variables = [];
        $properties = [];
        $holds = static fn (?Node $type): bool => $type instanceof Name && in_array(mb_ltrim($type->toString(), '\\'), $types, true);
        $finder = new NodeFinder();

        foreach ($finder->findInstanceOf($statements, Param::class) as $parameter) {
            if ($holds($parameter->type) && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                $variables[] = $parameter->var->name;

                if ($parameter->flags !== 0) {
                    $properties[] = $parameter->var->name;
                }
            }
        }

        foreach ($finder->findInstanceOf($statements, Property::class) as $property) {
            if ($holds($property->type)) {
                foreach ($property->props as $item) {
                    $properties[] = $item->name->toString();
                }
            }
        }

        return new self($types, $variables, $properties, $containerKeys);
    }

    public function holds(Expr $receiver): bool
    {
        return match (true) {
            $receiver instanceof Variable => is_string($receiver->name) && in_array($receiver->name, $this->variables, true),
            $receiver instanceof PropertyFetch => $receiver->var instanceof Variable && $receiver->var->name === 'this'
                && $receiver->name instanceof Identifier && in_array($receiver->name->toString(), $this->properties, true),
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
