<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Console\Scheduling\Schedule as Scheduler;
use Illuminate\Support\Facades\Schedule;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use RuntimeException;
use Tests\Support\Globs;

final readonly class ScheduledTaskGuard
{
    private const array SCHEDULE_FACADES = ['Schedule', Schedule::class];

    private const string MESSAGE = ': a scheduled task without ->withoutOverlapping(<minutes>): add it with the minutes after which a run that never finished frees the next one, so a slow run is never joined by a second';

    /**
     * @param list<string> $patterns
     */
    public function __construct(
        private string $root,
        private array $patterns,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];
        $files = [];

        foreach ($this->patterns as $pattern) {
            $files = [...$files, ...Globs::of($this->root . '/' . $pattern)];
        }

        if ($files === []) {
            throw new RuntimeException('No file matches ' . implode(', ', $this->patterns) . ': a guard that reads no file passes without checking');
        }

        foreach ($files as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $violation) {
                $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ':' . $violation;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $statements = PhpSource::resolved($source);
        $schedulers = self::schedulerParameters($statements);
        $held = [];
        $locked = [];
        $violations = [];

        foreach (new NodeFinder()->findInstanceOf($statements, Expression::class) as $statement) {
            $expression = $statement->expr;

            if ($expression instanceof Assign) {
                if ($expression->var instanceof Variable && is_string($expression->var->name) && $this->isTask($expression->expr, $schedulers)) {
                    $held[$expression->var->name] = $statement->getStartLine();
                }

                continue;
            }

            $base = $this->baseOf($expression);

            if ($base instanceof Variable && is_string($base->name) && array_key_exists($base->name, $held)) {
                $locked[$base->name] = ($locked[$base->name] ?? false) || $this->locks($expression);

                continue;
            }

            if ($this->isTask($expression, $schedulers) && ! $this->locks($expression)) {
                $violations[] = $statement->getStartLine() . self::MESSAGE;
            }
        }

        foreach ($held as $name => $line) {
            if (! ($locked[$name] ?? false)) {
                $violations[] = $line . self::MESSAGE;
            }
        }

        return $violations;
    }

    /**
     * @param array<array-key, Node> $statements
     * @return list<string>
     */
    private function schedulerParameters(array $statements): array
    {
        $names = [];

        foreach (new NodeFinder()->findInstanceOf($statements, FunctionLike::class) as $function) {
            foreach ($function->getParams() as $parameter) {
                $type = $parameter->type instanceof NullableType ? $parameter->type->type : $parameter->type;

                if ($type instanceof Name && mb_ltrim($type->toString(), '\\') === Scheduler::class && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                    $names[] = $parameter->var->name;
                }
            }
        }

        return $names;
    }

    private function baseOf(Expr $chain): Expr
    {
        while ($chain instanceof MethodCall) {
            $chain = $chain->var;
        }

        return $chain;
    }

    /**
     * @param list<string> $schedulers
     */
    private function isTask(Expr $chain, array $schedulers): bool
    {
        $base = $this->baseOf($chain);

        if ($base instanceof StaticCall) {
            return $base->class instanceof Name && in_array(mb_ltrim($base->class->toString(), '\\'), self::SCHEDULE_FACADES, true);
        }

        return $chain instanceof MethodCall
            && $base instanceof Variable
            && is_string($base->name)
            && in_array($base->name, $schedulers, true);
    }

    private function locks(Expr $chain): bool
    {
        while ($chain instanceof MethodCall) {
            if ($chain->name instanceof Identifier && $chain->name->toLowerString() === 'withoutoverlapping' && $chain->getArgs() !== []) {
                return true;
            }

            $chain = $chain->var;
        }

        return false;
    }
}
