<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

final readonly class RateLimiterGuard
{
    private const string NUMBER_IN_CONFIG = "the number of a limit lives in the limits config file (%s): Limit::perMinute(Config::integer('<file>.<name>'))->by(\$key), and the window is the factory (perMinute, perHour, perDay)";

    /**
     * @param list<string> $folders
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $folders,
        private string $limitsConfig,
        private string $provider,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return FolderScan::over($this->inspector, $this->root, $this->folders, fn (string $path, string $source): array => $this->inSource($source));
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $statements = PhpSource::resolved($source);
        $finder = new NodeFinder();
        $violations = [];

        foreach ($finder->find($statements, static fn (Node $node): bool => $node instanceof StaticCall || $node instanceof New_) as $call) {
            if (! $call instanceof StaticCall && ! $call instanceof New_ || ! $call->class instanceof Name || mb_ltrim($call->class->toString(), '\\') !== Limit::class) {
                continue;
            }

            $number = $finder->findFirst($call->args, static fn (Node $node): bool => $node instanceof Int_ || $node instanceof Float_
                || ($node instanceof ClassConstFetch && $node->name instanceof Identifier && $node->name->toLowerString() !== 'class'));

            if ($number instanceof Node) {
                $violations[] = $call->getStartLine() . ': a limit with a number or a constant in the code: ' . sprintf(self::NUMBER_IN_CONFIG, $this->limitsConfig);
            }
        }

        $limiters = TypedReceivers::in($statements, [RateLimiter::class, CacheRateLimiter::class]);

        foreach ($finder->find($statements, static fn (Node $node): bool => $node instanceof StaticCall || $node instanceof MethodCall) as $call) {
            if (! $call instanceof StaticCall && ! $call instanceof MethodCall || ! $call->name instanceof Identifier || $call->name->toLowerString() !== 'for') {
                continue;
            }

            $onLimiter = $call instanceof StaticCall
                ? $call->class instanceof Name && mb_ltrim($call->class->toString(), '\\') === RateLimiter::class
                : $limiters->holds($call->var);

            if (! $onLimiter) {
                continue;
            }

            $provider = $finder->findFirst($statements, fn (Node $node): bool => $node instanceof Class_
                && $node->namespacedName?->toString() === $this->provider
                && $node->getStartLine() <= $call->getStartLine() && $call->getEndLine() <= $node->getEndLine());

            if (! $provider instanceof Class_) {
                $violations[] = $call->getStartLine() . ': a limiter defined outside ' . $this->provider . ': every RateLimiter::for() lives in that provider, and a route takes it with throttle:<name>';
            }

            $name = $call->args[0] ?? null;

            if ($name instanceof Arg && $name->value instanceof String_ && preg_match('/^[a-z][a-z0-9]*(_[a-z0-9]+)*$/', $name->value->value) !== 1) {
                $violations[] = $call->getStartLine() . ': the limiter "' . $name->value->value . '" is not named in snake_case, like product_search';
            }
        }

        foreach ($finder->findInstanceOf($statements, String_::class) as $text) {
            if (preg_match('/^throttle:\d/', $text->value) === 1) {
                $violations[] = $text->getStartLine() . ': a throttle with numbers in the route ("' . $text->value . '"): ' . sprintf(self::NUMBER_IN_CONFIG, $this->limitsConfig) . '; the route takes the limiter with throttle:<name>';
            }
        }

        sort($violations, SORT_NATURAL);

        return $violations;
    }
}
