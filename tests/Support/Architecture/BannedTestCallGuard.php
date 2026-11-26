<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final readonly class BannedTestCallGuard
{
    private const array CLOCK_CLASSES = ['Carbon', 'CarbonImmutable', 'Date', 'Illuminate\\Support\\' . 'Carbon', 'Carbon\\' . 'Carbon', 'Carbon\\' . 'CarbonImmutable', 'Illuminate\\Support\\Facades\\' . 'Date'];

    private const array BINDINGS = ['instance', 'bind', 'singleton', 'scoped', 'bindif', 'singletonif', 'extend'];

    private const array APP_FACADES = [App::class, 'App'];

    private const array PEST_TEST_FUNCTIONS = ['it', 'test', 'describe', 'arch', 'todo'];

    private const array STRAY_SWITCHES = ['allowstrayrequests' => 'allowStrayRequests()', 'allowstrayprocesses' => 'allowStrayProcesses()'];

    private const array STRAY_PREVENTERS = ['preventstrayrequests' => 'preventStrayRequests(false)', 'preventstrayprocesses' => 'preventStrayProcesses(false)'];

    private const array LOOSE_EXPECTATIONS = ['tobetruthy' => 'toBeTruthy(): assert the exact value with toBeTrue(), toBe() or not->toBeEmpty()', 'tobefalsy' => 'toBeFalsy(): assert the exact value with toBeFalse(), toBeNull() or toBeEmpty()'];

    private const array HANDLING_SWITCHES = [
        'withoutexceptionhandling' => 'withoutExceptionHandling(): assert the response (assertServerError(), assertSessionHasNoErrors()), or call the class directly and expect(fn () => ...)->toThrow()',
        'withoutmiddleware' => 'withoutMiddleware(): send the request through its middleware, as a signed-in user with the permission the route asks for',
    ];

    private const array TEST_CLOSURE_FUNCTIONS = ['it', 'test', 'describe', 'beforeeach', 'aftereach', 'beforeall', 'afterall'];

    private const array NAMED_STATUSES = [
        200 => 'assertOk',
        201 => 'assertCreated',
        202 => 'assertAccepted',
        204 => 'assertNoContent',
        301 => 'assertMovedPermanently',
        302 => 'assertFound',
        304 => 'assertNotModified',
        307 => 'assertTemporaryRedirect',
        308 => 'assertPermanentRedirect',
        400 => 'assertBadRequest',
        401 => 'assertUnauthorized',
        402 => 'assertPaymentRequired',
        403 => 'assertForbidden',
        404 => 'assertNotFound',
        405 => 'assertMethodNotAllowed',
        406 => 'assertNotAcceptable',
        408 => 'assertRequestTimeout',
        409 => 'assertConflict',
        410 => 'assertGone',
        415 => 'assertUnsupportedMediaType',
        422 => 'assertUnprocessable',
        424 => 'assertFailedDependency',
        429 => 'assertTooManyRequests',
        500 => 'assertInternalServerError',
        503 => 'assertServiceUnavailable',
    ];

    /**
     * @param list<string> $withoutTestClock
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $tests,
        private array $withoutTestClock,
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->tests) as $file) {
            $relative = mb_substr($file, mb_strlen($this->root) + 1);
            $found = $this->inFile($file, $relative);

            if ($found !== []) {
                $violations[$relative] = $found;
            }
        }

        ksort($violations);

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function inFile(string $file, string $relative): array
    {
        $found = [];

        foreach ($this->inspector->calls((string) file_get_contents($file)) as $call) {
            $reason = $this->reasonToBan($call, $relative);

            if ($reason !== null) {
                $found[] = 'line ' . $call['line'] . ': ' . $reason;
            }
        }

        return [...$found, ...$this->chainedOnATest((string) file_get_contents($file))];
    }

    /**
     * @return list<string>
     */
    private function chainedOnATest(string $source): array
    {
        $found = [];
        $statements = new ParserFactory()->createForHostVersion()->parse($source) ?? [];

        foreach (new NodeFinder()->find($statements, static fn (Node $node): bool => $node instanceof MethodCall || $node instanceof NullsafeMethodCall) as $call) {
            if (! $call instanceof MethodCall && ! $call instanceof NullsafeMethodCall || ! $call->name instanceof Identifier) {
                continue;
            }

            $name = $call->name->toLowerString();
            $root = $this->chainRoot($call->var);
            $line = 'line ' . $call->name->getStartLine() . ': ';

            if ($name === 'only' && in_array($root, self::PEST_TEST_FUNCTIONS, true)) {
                $found[] = $line . '->only() on a test: remove it, a focused test skips every other test of the run; run one test with --filter';
            }

            if ($name === 'withoutmiddleware' && $root !== null) {
                $found[] = $line . self::HANDLING_SWITCHES[$name];
            }

            $status = $call->isFirstClassCallable() ? null : ($call->getArgs()[0]->value ?? null);

            if ($name === 'assertstatus' && $status instanceof Int_ && isset(self::NAMED_STATUSES[$status->value])) {
                $found[] = $line . 'assertStatus(' . $status->value . '): write ' . self::NAMED_STATUSES[$status->value] . '()';
            }
        }

        return [...$found, ...$this->oldTestSpellings($statements)];
    }

    /**
     * @param array<Node> $statements
     * @return list<string>
     */
    private function oldTestSpellings(array $statements): array
    {
        $found = [];

        foreach (new NodeFinder()->findInstanceOf($statements, FuncCall::class) as $call) {
            if (! $call->name instanceof Name || $call->isFirstClassCallable() || $call->getArgs() === []) {
                continue;
            }

            $name = $call->name->toLowerString();
            $arguments = $call->getArgs();
            $description = $arguments[0]->value;
            $closure = $arguments[count($arguments) - 1]->value;
            $line = 'line ' . $call->getStartLine() . ': ';

            if ($name === 'test') {
                $found[] = $line . "test(): write it('does x', function (): void { ... }), the one spelling of a test";
            }

            if ($name === 'it' && $description instanceof String_ && preg_match('/^it\b/i', $description->value) === 1) {
                $found[] = $line . 'it(\'it ...\'): Pest puts "it" in front of the description, write it(\'does x\')';
            }

            if (in_array($name, self::TEST_CLOSURE_FUNCTIONS, true) && $closure instanceof Closure && (! $closure->returnType instanceof Identifier || $closure->returnType->toLowerString() !== 'void')) {
                $found[] = $line . 'a closure of ' . $name . '() without a return type: write function (): void';
            }
        }

        return $found;
    }

    private function chainRoot(Node $expression): ?string
    {
        while ($expression instanceof MethodCall || $expression instanceof NullsafeMethodCall || $expression instanceof PropertyFetch) {
            $expression = $expression->var;
        }

        if ($expression instanceof Variable) {
            return $expression->name === 'this' ? '$this' : null;
        }

        return $expression instanceof FuncCall && $expression->name instanceof Name ? $expression->name->getLast() : null;
    }

    /**
     * @param array{kind: string, line: int, classLine: int, name: string, written: string, class: string|null, receiver: string|null, arguments: list<string>} $call
     */
    private function reasonToBan(array $call, string $relative): ?string
    {
        if ($call['kind'] === 'static' && $call['name'] === 'set' && in_array($call['class'], [Config::class, 'Config'], true)) {
            return 'Config::set(): write config([\'key\' => $value])';
        }

        if ($call['kind'] === 'method' && $call['name'] === 'set' && in_array($call['receiver'], ['config()', "app('config')", "->app['config']"], true)) {
            return 'config()->set(): write config([\'key\' => $value])';
        }

        if ($call['kind'] === 'static' && $call['name'] === 'settestnow' && in_array($call['class'], self::CLOCK_CLASSES, true) && ! $this->isClockExempt($relative)) {
            return 'setTestNow(): use $this->travelTo(), $this->freezeTime() or test()->travelTo(); only a test in a folder without a test clock (the pure unit tier, a test concern) may call it';
        }

        if ($call['kind'] === 'method' && $call['receiver'] === '->app' && in_array($call['name'], self::BINDINGS, true)) {
            return '$this->app->' . $call['name'] . '(): write app()->instance(Abstract::class, $fake)';
        }

        if ($call['kind'] === 'method' && $call['receiver'] === '$this' && $call['name'] === 'instance') {
            return '$this->instance(): write app()->instance(Abstract::class, $fake)';
        }

        if ($call['kind'] === 'static' && $call['name'] === 'instance' && in_array($call['class'], self::APP_FACADES, true)) {
            return 'App::instance(): write app()->instance(Abstract::class, $fake)';
        }

        if ($call['kind'] === 'method' && $call['receiver'] === 'app()' && $call['name'] !== 'instance' && in_array($call['name'], self::BINDINGS, true) && $this->returnsReadyObject($call['arguments'][1] ?? '')) {
            return 'app()->' . $call['name'] . '() of a ready object: write app()->instance(Abstract::class, $fake)';
        }

        if ($call['kind'] === 'static' && $call['name'] !== 'instance' && in_array($call['class'], self::APP_FACADES, true) && in_array($call['name'], self::BINDINGS, true) && $this->returnsReadyObject($call['arguments'][1] ?? '')) {
            return 'App::' . $call['name'] . '() of a ready object: write app()->instance(Abstract::class, $fake)';
        }

        if ($call['kind'] === 'function' && $call['name'] === 'expect' && count($call['arguments']) === 1 && mb_ltrim(mb_strtolower($call['arguments'][0]), '\\') === 'true') {
            return 'expect(true): assert the state the code leaves behind, or end the test with ->throwsNoExceptions()';
        }

        if ($call['kind'] === 'method' && $call['receiver'] === '->not' && $call['name'] === 'tothrow') {
            return '->not->toThrow(): call the code directly so an exception fails the test, and assert the state it leaves behind';
        }

        if ($call['kind'] === 'function' && in_array($call['name'], ['sleep', 'usleep'], true)) {
            return $call['name'] . '(): wait with Sleep::for(...)->seconds(), which the base set-up fakes, or move the clock with $this->travelTo()';
        }

        if (isset(self::STRAY_SWITCHES[$call['name']])) {
            return self::STRAY_SWITCHES[$call['name']] . ': a test fakes the requests and processes it expects, the stray guard stays on';
        }

        if (isset(self::STRAY_PREVENTERS[$call['name']]) && mb_ltrim(mb_strtolower($call['arguments'][0] ?? ''), '\\') === 'false') {
            return self::STRAY_PREVENTERS[$call['name']] . ': a test fakes the requests and processes it expects, the stray guard stays on';
        }

        if ($call['kind'] === 'method' && $call['name'] === 'ignoring') {
            return 'ignoring() on an architecture rule: the rule has no exception, fix the code';
        }

        if ($call['kind'] !== 'static' && isset(self::LOOSE_EXPECTATIONS[$call['name']])) {
            return self::LOOSE_EXPECTATIONS[$call['name']];
        }

        if ($call['kind'] !== 'static' && $call['name'] === 'withoutexceptionhandling') {
            return self::HANDLING_SWITCHES[$call['name']];
        }

        if ($call['kind'] === 'function' && $call['name'] === 'withoutmiddleware') {
            return self::HANDLING_SWITCHES[$call['name']];
        }

        if ($call['kind'] === 'method' && $call['name'] === 'tothrow' && $this->dropsItsResult($call['arguments'][0] ?? '')) {
            return 'toThrow() with a closure whose result Pest drops: assert inside the closure with expect()';
        }

        return null;
    }

    private function dropsItsResult(string $closure): bool
    {
        return preg_match('/^(static )?(fn|function)\s*\((?:[^{=]|=(?!>))*?\)\s*:\s*bool\b/', $closure) === 1
            || preg_match('/^(static )?fn\s*\([^)]*\)\s*(:\s*\S+\s*)?=>(?!\s*\\\\?expect\()/', $closure) === 1;
    }

    private function isClockExempt(string $relative): bool
    {
        return array_any($this->withoutTestClock, static fn (string $folder): bool => str_starts_with($relative, mb_rtrim($folder, '/') . '/'));
    }

    private function returnsReadyObject(string $closure): bool
    {
        return preg_match('/^(static )?fn\(\)(: \S+)? => \$\w+$/', $closure) === 1;
    }
}
