<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;

final class PestBindingReader
{
    /**
     * @return list<array{bound: list<string>, targets: list<string>|null}>
     */
    public static function pestBindings(string $file): array
    {
        $bindings = [];
        $finder = new NodeFinder();

        foreach (PhpSource::statements($file) as $statement) {
            $calls = $finder->find(
                $statement,
                static fn (Node $node): bool => $node instanceof MethodCall || $node instanceof FuncCall,
            );

            $inner = [];

            foreach ($calls as $call) {
                if ($call instanceof MethodCall) {
                    $inner[spl_object_id($call->var)] = true;
                }
            }

            foreach ($calls as $call) {
                if (isset($inner[spl_object_id($call)])) {
                    continue;
                }

                $binding = self::bindingOf($call);

                if ($binding !== null) {
                    $bindings[] = $binding;
                }
            }
        }

        return $bindings;
    }

    /**
     * @return array{bound: list<string>, targets: list<string>|null}|null
     */
    private static function bindingOf(Node $outermost): ?array
    {
        $bound = [];
        $targets = null;
        $isBinding = false;
        $cursor = $outermost;

        while ($cursor instanceof MethodCall || $cursor instanceof FuncCall) {
            $name = $cursor->name instanceof Identifier || $cursor->name instanceof Name ? $cursor->name->toString() : '';
            $lower = mb_strtolower(mb_ltrim($name, '\\'));

            if (in_array($lower, ['extend', 'use', 'uses'], true)) {
                $isBinding = true;

                foreach ($cursor->args as $argument) {
                    if (! $argument instanceof Arg) {
                        continue;
                    }

                    $value = $argument->value;

                    if ($value instanceof ClassConstFetch && $value->class instanceof Name) {
                        $bound[] = mb_ltrim($value->class->toString(), '\\');
                    } elseif ($value instanceof String_) {
                        $bound[] = mb_ltrim($value->value, '\\');
                    }
                }
            }

            if ($lower === 'in') {
                $targets = [];

                foreach ($cursor->args as $argument) {
                    if ($argument instanceof Arg) {
                        array_push($targets, ...self::pathStrings($argument->value));
                    }
                }
            }

            $cursor = $cursor instanceof MethodCall ? $cursor->var : null;
        }

        return $isBinding ? ['bound' => $bound, 'targets' => $targets] : null;
    }

    /**
     * @return list<string>
     */
    private static function pathStrings(Node $expression): array
    {
        if ($expression instanceof String_) {
            return [$expression->value];
        }

        if ($expression instanceof Dir) {
            return [''];
        }

        if ($expression instanceof Concat) {
            $left = self::pathStrings($expression->left);
            $right = self::pathStrings($expression->right);

            return [implode('', $left) . implode('', $right)];
        }

        if ($expression instanceof Array_) {
            $paths = [];

            foreach ($expression->items as $item) {
                array_push($paths, ...self::pathStrings($item->value));
            }

            return $paths;
        }

        return ['*'];
    }
}
