<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;

final class PestExampleReader
{
    private const array FAILING_MATCHERS = ['tocontain', 'tostartwith', 'toendwith', 'tomatch', 'tohavekey', 'tohavecount', 'tobefalse'];

    private const array PASSING_MATCHERS = ['tobeempty', 'tobetrue', 'tobenull'];

    /**
     * @param list<string> $groups
     * @return array{group: bool, failing: bool, passing: bool, realTree: bool}
     */
    public static function parts(string $file, array $groups): array
    {
        $statements = PhpSource::statements($file);
        $finder = new NodeFinder();
        $chains = self::chains($finder->findInstanceOf($statements, MethodCall::class));
        $tests = array_values(array_filter(
            $finder->findInstanceOf($statements, FuncCall::class),
            static fn (FuncCall $call): bool => self::functionName($call) === 'it',
        ));
        $failing = false;
        $passing = false;
        $realTree = false;
        $grouped = $tests !== [];

        foreach ($tests as $test) {
            $methods = $chains[spl_object_id($test)] ?? [];
            $body = self::body($test);
            $asserts = self::asserts($finder, $body);
            $isReal = ! array_key_exists('with', $methods) && self::readsOwnTree($finder, $body) && $asserts['passing'];

            $failing = $failing || (! $isReal && $asserts['failing']);
            $realTree = $realTree || $isReal;
            $passing = $passing || (! $isReal && $asserts['passing']);
            $grouped = $grouped && self::containsGroup($methods, $groups);
        }

        return ['group' => $grouped || self::fileLevelGroup($finder, $statements, $groups), 'failing' => $failing, 'passing' => $passing, 'realTree' => $realTree];
    }

    /**
     * @param array<MethodCall> $calls
     * @return array<int, array<string, list<string>>>
     */
    private static function chains(array $calls): array
    {
        $chains = [];

        foreach ($calls as $call) {
            $root = $call;

            while ($root instanceof MethodCall) {
                $root = $root->var;
            }

            if ($root instanceof FuncCall && $call->name instanceof Identifier) {
                $chains[spl_object_id($root)][$call->name->toLowerString()] = self::stringArguments($call);
            }
        }

        return $chains;
    }

    /**
     * @param array<string, list<string>> $methods
     * @param list<string> $groups
     */
    private static function containsGroup(array $methods, array $groups): bool
    {
        return array_key_exists('group', $methods) && array_intersect($groups, $methods['group']) !== [];
    }

    /**
     * @param array<array-key, Node> $statements
     * @param list<string> $groups
     */
    private static function fileLevelGroup(NodeFinder $finder, array $statements, array $groups): bool
    {
        foreach ($finder->findInstanceOf($statements, MethodCall::class) as $call) {
            $root = $call;

            while ($root instanceof MethodCall) {
                $root = $root->var;
            }

            if ($call->name instanceof Identifier && $call->name->toLowerString() === 'group' && $root instanceof FuncCall
                && in_array(self::functionName($root), ['uses', 'pest'], true) && array_intersect($groups, self::stringArguments($call)) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<array-key, Node>
     */
    private static function body(FuncCall $test): array
    {
        $closure = ($test->getArgs()[1] ?? null)?->value;

        return $closure instanceof Closure || $closure instanceof ArrowFunction ? [$closure] : [];
    }

    /**
     * @param array<array-key, Node> $body
     * @return array{failing: bool, passing: bool}
     */
    private static function asserts(NodeFinder $finder, array $body): array
    {
        $failing = false;
        $passing = false;

        foreach ($finder->findInstanceOf($body, MethodCall::class) as $call) {
            $name = $call->name instanceof Identifier ? $call->name->toLowerString() : '';
            $first = ($call->getArgs()[0] ?? null)?->value;
            $exact = in_array($name, ['tobe', 'toequal'], true);

            $failing = $failing || in_array($name, self::FAILING_MATCHERS, true) || ($exact && ! self::isEmpty($first));
            $passing = $passing || in_array($name, self::PASSING_MATCHERS, true) || ($exact && self::isEmpty($first));
        }

        return ['failing' => $failing, 'passing' => $passing];
    }

    private static function isEmpty(?Expr $expression): bool
    {
        return $expression instanceof Array_ && $expression->items === [];
    }

    /**
     * @param array<array-key, Node> $body
     */
    private static function readsOwnTree(NodeFinder $finder, array $body): bool
    {
        $dirname = array_any(
            $finder->findInstanceOf($body, FuncCall::class),
            static fn (FuncCall $call): bool => self::functionName($call) === 'dirname' && ($call->getArgs()[0] ?? null)?->value instanceof Dir,
        );

        $calls = [...$finder->findInstanceOf($body, MethodCall::class), ...$finder->findInstanceOf($body, StaticCall::class)];

        return $dirname && array_any(
            $calls,
            static fn (MethodCall|StaticCall $call): bool => $call->name instanceof Identifier && preg_match('/violations|findings/i', $call->name->toString()) === 1,
        );
    }

    private static function functionName(FuncCall $call): string
    {
        return $call->name instanceof Name ? $call->name->toLowerString() : '';
    }

    /**
     * @return list<string>
     */
    private static function stringArguments(MethodCall $call): array
    {
        $strings = [];

        foreach ($call->args as $argument) {
            if ($argument instanceof Arg && $argument->value instanceof String_) {
                $strings[] = $argument->value->value;
            }
        }

        return $strings;
    }
}
