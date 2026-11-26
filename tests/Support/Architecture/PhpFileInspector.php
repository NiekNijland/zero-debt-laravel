<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Support\Facades\Exceptions;
use InvalidArgumentException;
use Override;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use Tests\Support\RecursiveFiles;

final class PhpFileInspector
{
    private const array BROAD_EXCEPTIONS = ['Throwable', 'Exception', 'Error', 'ErrorException', 'RuntimeException', 'LogicException'];

    /** @var array<string, list<Node\Stmt>> */
    private array $parsed = [];

    /** @var array<string, list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}>> */
    private static array $calls = [];

    /**
     * @return array{inside: list<string>, outside: list<string>}
     */
    public function referencedNamesByFactory(string $file): array
    {
        $visitor = new class() extends NodeVisitorAbstract
        {
            /** @var list<string> */
            public array $inside = [];

            /** @var list<string> */
            public array $outside = [];

            private int $depth = 0;

            #[Override]
            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Use_ || $node instanceof GroupUse) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof ClassMethod && $node->isStatic() && str_starts_with($node->name->toString(), 'from')) {
                    $this->depth++;
                }

                if ($node instanceof Name) {
                    $this->record(mb_ltrim($node->toString(), '\\'));
                }

                if ($node instanceof String_ && preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $node->value) === 1) {
                    $this->record(mb_ltrim($node->value, '\\'));
                }

                return null;
            }

            #[Override]
            public function leaveNode(Node $node): null
            {
                if ($node instanceof ClassMethod && $node->isStatic() && str_starts_with($node->name->toString(), 'from')) {
                    $this->depth--;
                }

                return null;
            }

            private function record(string $name): void
            {
                if ($this->depth > 0) {
                    $this->inside[] = $name;
                } else {
                    $this->outside[] = $name;
                }
            }
        };

        PhpSource::traverse($this->statements($file), $visitor);

        return ['inside' => array_values(array_unique($visitor->inside)), 'outside' => array_values(array_unique($visitor->outside))];
    }

    /**
     * @return list<string>
     */
    public function referencedNames(string $file): array
    {
        $names = [];

        foreach ($this->statements($file) as $statement) {
            foreach (new NodeFinder()->findInstanceOf($statement, Name::class) as $name) {
                $names[] = mb_ltrim($name->toString(), '\\');
            }

            foreach (new NodeFinder()->findInstanceOf($statement, String_::class) as $string) {
                if (preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $string->value) === 1) {
                    $names[] = mb_ltrim($string->value, '\\');
                }
            }

            foreach (new NodeFinder()->findInstanceOf($statement, GroupUse::class) as $group) {
                foreach ($group->uses as $use) {
                    $names[] = mb_ltrim($group->prefix->toString() . '\\' . $use->name->toString(), '\\');
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<string, list<string>>
     */
    public function declaredTypes(string $file): array
    {
        $types = [];

        foreach ($this->statements($file) as $statement) {
            $declarations = new NodeFinder()->find(
                $statement,
                static fn (Node $node): bool => $node instanceof Class_ || $node instanceof Trait_ || $node instanceof Interface_ || $node instanceof Enum_,
            );

            foreach ($declarations as $declaration) {
                if (! $declaration instanceof ClassLike || ! $declaration->namespacedName instanceof Name) {
                    continue;
                }

                $related = [];

                foreach (new NodeFinder()->findInstanceOf($declaration, Name::class) as $name) {
                    $related[] = mb_ltrim($name->toString(), '\\');
                }

                $types[$declaration->namespacedName->toString()] = array_values(array_unique($related));
            }
        }

        return $types;
    }

    /**
     * @return list<int>
     */
    public function swallowedBroadCatchLines(string $file): array
    {
        $lines = [];

        foreach ($this->statements($file) as $statement) {
            foreach (new NodeFinder()->findInstanceOf($statement, Catch_::class) as $catch) {
                $broad = array_filter($catch->types, static fn (Name $type): bool => in_array($type->toString(), self::BROAD_EXCEPTIONS, true)) !== [];

                if ($broad && ! $this->reportsOrRethrows($catch)) {
                    $lines[] = $catch->getStartLine();
                }
            }
        }

        sort($lines);

        return array_values(array_unique($lines));
    }

    /**
     * @return list<string>
     */
    public function phpFiles(string $directory, bool $mayBeAbsent = false): array
    {
        if (! is_dir($directory)) {
            if ($mayBeAbsent) {
                return [];
            }

            throw new InvalidArgumentException('The directory ' . $directory . ' does not exist: a guard over a missing folder would pass without reading a file');
        }

        $files = [];

        foreach (RecursiveFiles::in($directory) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}>
     */
    public function callsIn(string $file): array
    {

        $key = $file . '@' . md5_file($file);

        return self::$calls[$key] ??= $this->calls((string) file_get_contents($file));
    }

    /**
     * @return list<array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>}>
     */
    public function calls(string $source): array
    {
        return PhpCallReader::calls($source);
    }

    /**
     * @return list<string>
     */
    public function stringLiterals(string $file): array
    {
        $values = [];

        foreach ($this->statements($file) as $statement) {
            foreach (new NodeFinder()->find($statement, static fn (Node $node): bool => $node instanceof String_ || $node instanceof InterpolatedStringPart) as $string) {
                if ($string instanceof String_ || $string instanceof InterpolatedStringPart) {
                    $values[] = $string->value;
                }
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    public function keyedArrayStrings(string $file): array
    {
        $values = [];

        foreach ($this->statements($file) as $statement) {
            foreach (new NodeFinder()->findInstanceOf($statement, ArrayItem::class) as $item) {
                if ($item->key !== null && $item->value instanceof String_) {
                    $values[] = $item->value->value;
                }
            }
        }

        return $values;
    }

    private function reportsOrRethrows(Catch_ $catch): bool
    {
        return new NodeFinder()->findFirst($catch->stmts, static function (Node $node): bool {
            $name = match (true) {
                $node instanceof FuncCall && $node->name instanceof Name => $node->name->toString(),
                ($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall) && $node->name instanceof Identifier => $node->name->toString(),
                default => null,
            };

            return $node instanceof Throw_
                || $name === 'rescue'
                || ($name !== null && str_starts_with($name, 'report'))
                || ($node instanceof StaticCall && $node->class instanceof Name && $node->class->toString() === Exceptions::class)
                || ($node instanceof MethodCall && $name === 'fail' && $node->var instanceof Variable && $node->var->name === 'this');
        }) instanceof Node;
    }

    /**
     * @return list<Node\Stmt>
     */
    private function statements(string $file): array
    {
        return $this->parsed[$file] ??= PhpSource::statements($file);
    }
}
