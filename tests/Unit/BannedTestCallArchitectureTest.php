<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Tests\Support\Architecture\BannedTestCallGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$mutable = 'Carbo' . 'n';

dataset('banned test calls', [
    'Config::set with a key and a value' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\Config;\nConfig::set('app.name', 'x');\n", 'Config::set()'],
    'Config::set with an array' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\Config;\nConfig::set(['app.name' => 'x']);\n", 'Config::set()'],
    'Config::set by its fully qualified name' => ['tests/Support/Foo.php', '<?php
' . Config::class . '::set(\'app.name\', \'x\');
', 'Config::set()'],
    'config set through the container string' => ['tests/Feature/FooTest.php', "<?php\napp('config')->set('app.name', 'x');\n", 'config()->set()'],
    'config set through the container as an array' => ['tests/Feature/FooTest.php', "<?php\nfinal class T { public function f(): void { \$this->app['config']->set('app.name', 'x'); } }\n", 'config()->set()'],
    'expect(TRUE) in capitals' => ['tests/Feature/FooTest.php', "<?php\nexpect(TRUE)->toBeTrue();\n", 'expect(true)'],
    'config()->set' => ['tests/Feature/FooTest.php', "<?php\nconfig()->set('app.name', 'x');\n", 'config()->set()'],
    'setTestNow in a feature test' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\Date;\nDate::setTestNow('2025-01-02');\n", 'setTestNow()'],
    'setTestNow of the mutable date class, built from parts' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\" . $mutable . ";\n" . $mutable . "::setTestNow('2025-01-02');\n", 'setTestNow()'],
    'setTestNow of the immutable class in a helper' => ['tests/Support/Foo.php', "<?php\nuse Carbon\\CarbonImmutable;\nCarbonImmutable::setTestNow();\n", 'setTestNow()'],
    'setTestNow through the Date facade in a feature test' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\Date;\nDate::setTestNow('2025-01-02');\n", 'setTestNow()'],
    '$this->app->instance' => ['tests/Feature/FooTest.php', "<?php\n\$this->app->instance(Foo::class, \$foo);\n", '$this->app->instance()'],
    '$this->app->bind' => ['tests/Feature/FooTest.php', "<?php\n\$this->app->bind(Foo::class, fn () => \$foo);\n", '$this->app->bind()'],
    '$this->app->singleton' => ['tests/Feature/FooTest.php', "<?php\n\$this->app->singleton(Foo::class, fn () => new Foo());\n", '$this->app->singleton()'],
    '$this->instance' => ['tests/Feature/FooTest.php', "<?php\n\$this->instance(Foo::class, \$foo);\n", '$this->instance()'],
    'app()->bind of a ready object' => ['tests/Feature/FooTest.php', "<?php\napp()->bind(Foo::class, fn () => \$foo);\n", 'app()->bind() of a ready object'],
    'app()->bind of a ready object with a return type' => ['tests/Feature/FooTest.php', "<?php\napp()->bind(Foo::class, static fn (): Foo => \$foo);\n", 'app()->bind() of a ready object'],
    'expect(true)' => ['tests/Feature/FooTest.php', "<?php\nexpect(true)->toBeTrue();\n", 'expect(true)'],
    'not->toThrow' => ['tests/Feature/FooTest.php', "<?php\nexpect(\$run)->not->toThrow(RuntimeException::class);\n", '->not->toThrow()'],
    'not->toThrow of an interface' => ['tests/Feature/FooTest.php', "<?php\nexpect(fn () => \$run())->not->toThrow(Throwable::class);\n", '->not->toThrow()'],
    'toThrow with an arrow function that returns a bool' => ['tests/Feature/FooTest.php', "<?php\nexpect(\$run)->toThrow(fn (FooException \$e): bool => \$e->reason === 'x');\n", 'toThrow()'],
    'toThrow with a function that returns a bool' => ['tests/Feature/FooTest.php', "<?php\nexpect(\$run)->toThrow(function (FooException \$e): bool { return \$e->reason === 'x'; });\n", 'toThrow()'],
    'toThrow with an arrow function that compares' => ['tests/Feature/FooTest.php', "<?php\nexpect(\$run)->toThrow(fn (FooException \$e) => \$e->reason === 'x');\n", 'toThrow()'],
    'app()->singleton of a ready object' => ['tests/Feature/FooTest.php', "<?php\napp()->singleton(Foo::class, fn () => \$foo);\n", 'app()->singleton() of a ready object'],
    'app()->scoped of a ready object' => ['tests/Feature/FooTest.php', "<?php\napp()->scoped(Foo::class, fn () => \$foo);\n", 'app()->scoped() of a ready object'],
    'app()->bindIf of a ready object' => ['tests/Feature/FooTest.php', "<?php\napp()->bindIf(Foo::class, fn () => \$foo);\n", 'app()->bindif() of a ready object'],
    'app()->extend of a ready object' => ['tests/Feature/FooTest.php', "<?php\napp()->extend(Foo::class, fn () => \$foo);\n", 'app()->extend() of a ready object'],
    'App::instance' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\App;\nApp::instance(Foo::class, \$foo);\n", 'App::instance()'],
    'App::bind of a ready object' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\App;\nApp::bind(Foo::class, fn () => \$foo);\n", 'App::bind() of a ready object'],
    'App::singleton of a ready object' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\App;\nApp::singleton(Foo::class, fn () => \$foo);\n", 'App::singleton() of a ready object'],
    'App::scoped of a ready object' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\App;\nApp::scoped(Foo::class, fn () => \$foo);\n", 'App::scoped() of a ready object'],
    'a focused test' => ['tests/Feature/FooTest.php', "<?php\nit('works', function (): void {\n    expect(\$a)->toBe(1);\n})->only();\n", '->only() on a test'],
    'a focused test with a dataset' => ['tests/Feature/FooTest.php', "<?php\nit('works', fn () => expect(\$a)->toBe(1))->with([1, 2])->only();\n", '->only() on a test'],
    'a focused describe block' => ['tests/Unit/FooTest.php', "<?php\ndescribe('a', function (): void {})->only();\n", '->only() on a test'],
    'withoutExceptionHandling on the test case' => ['tests/Feature/FooTest.php', "<?php\n\$this->withoutExceptionHandling();\n", 'withoutExceptionHandling()'],
    'withoutExceptionHandling in a request chain' => ['tests/Feature/FooTest.php', "<?php\n\$this->actingAs(\$user)->withoutExceptionHandling()->post('/x');\n", 'withoutExceptionHandling()'],
    'withoutExceptionHandling of Pest' => ['tests/Feature/FooTest.php', "<?php\nuse function Pest\\Laravel\\withoutExceptionHandling;\nwithoutExceptionHandling();\n", 'withoutExceptionHandling()'],
    'withoutMiddleware on the test case' => ['tests/Feature/FooTest.php', "<?php\n\$this->withoutMiddleware()->get('/x');\n", 'withoutMiddleware()'],
    'withoutMiddleware in a helper' => ['tests/Support/Foo.php', "<?php\ntest()->actingAs(\$user)->withoutMiddleware(Foo::class)->get('/x');\n", 'withoutMiddleware()'],
    'withoutMiddleware of Pest' => ['tests/Feature/FooTest.php', "<?php\nuse function Pest\\Laravel\\withoutMiddleware;\nwithoutMiddleware();\n", 'withoutMiddleware()'],
    'toBeTruthy' => ['tests/Feature/FooTest.php', "<?php\nexpect(\$value)->toBeTruthy();\n", 'toBeTruthy()'],
    'toBeFalsy' => ['tests/Feature/FooTest.php', "<?php\nexpect(\$value)->not->toBeNull()->and(\$other)->toBeFalsy();\n", 'toBeFalsy()'],
    'a test whose description starts with it' => ['tests/Feature/FooTest.php', "<?php\nit('it works', function (): void {\n    expect(\$a)->toBe(1);\n});\n", "it('it ...')"],
    'the test function' => ['tests/Feature/FooTest.php', "<?php\ntest('a user can sign in', function (): void {\n    expect(\$a)->toBe(1);\n});\n", 'test(): write it('],
    'the test function by its full name' => ['tests/Feature/FooTest.php', "<?php\n\\test('a user can sign in', function (): void {\n    expect(\$a)->toBe(1);\n});\n", 'test(): write it('],
    'sleep' => ['tests/Feature/FooTest.php', "<?php\nsleep(1);\n", 'sleep(): wait with Sleep::for'],
    'usleep by its full name' => ['tests/Feature/FooTest.php', "<?php\n\\usleep(100);\n", 'usleep(): wait with Sleep::for'],
    'allowStrayRequests on the facade' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\Http;\nHttp::allowStrayRequests();\n", 'allowStrayRequests()'],
    'allowStrayProcesses on the facade by its full name' => ['tests/Feature/FooTest.php', '<?php
' . Process::class . '::allowStrayProcesses();
', 'allowStrayProcesses()'],
    'allowStrayRequests on the factory' => ['tests/Feature/FooTest.php', "<?php\n\$factory->allowStrayRequests(['*']);\n", 'allowStrayRequests()'],
    'preventStrayRequests with false' => ['tests/Feature/FooTest.php', "<?php\nHttp::preventStrayRequests(false);\n", 'preventStrayRequests(false)'],
    'preventStrayProcesses with FALSE' => ['tests/Feature/FooTest.php', "<?php\nProcess::preventStrayProcesses(FALSE);\n", 'preventStrayProcesses(false)'],
    'ignoring on an architecture preset' => ['tests/Unit/FooTest.php', "<?php\narch()->preset()->php()->ignoring('App\\Legacy');\n", 'ignoring()'],
    'a test closure without a return type' => ['tests/Feature/FooTest.php', "<?php\nit('works', function () {\n    expect(\$a)->toBe(1);\n});\n", 'a closure of it() without a return type'],
    'a test closure with a dataset and without a return type' => ['tests/Unit/FooTest.php', "<?php\nit('a number is positive', function (int \$a) {\n    expect(\$a)->toBeGreaterThan(0);\n})->with([1, 2]);\n", 'a closure of it() without a return type'],
    'a hook closure without a return type' => ['tests/Feature/FooTest.php', "<?php\nbeforeEach(function () {\n    config(['app.name' => 'x']);\n});\n", 'a closure of beforeeach() without a return type'],
    'a describe closure without a return type' => ['tests/Feature/FooTest.php', "<?php\ndescribe('a', function () {\n    it('works', function (): void {\n        expect(\$a)->toBe(1);\n    });\n});\n", 'a closure of describe() without a return type'],
    'assertStatus with the code of assertOk' => ['tests/Feature/FooTest.php', "<?php\n\$this->get(route('a'))->assertStatus(200);\n", 'assertStatus(200): write assertOk()'],
    'assertStatus with the code of assertUnprocessable' => ['tests/Support/Foo.php', "<?php\n\$response->assertStatus(422);\n", 'assertStatus(422): write assertUnprocessable()'],
]);

it('finds every banned spelling in a test file', function (string $path, string $source, string $call): void {
    $root = SourceSandbox::create([$path => $source]);

    try {
        $violations = new BannedTestCallGuard(new PhpFileInspector(), $root, 'tests', ['tests/Unit'])->violations();

        expect($violations)->toHaveKey($path)
            ->and($violations[$path])->toHaveCount(1)
            ->and($violations[$path][0])->toContain($call);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('banned test calls');

dataset('allowed test calls', [
    'config with an array' => ['tests/Feature/FooTest.php', "<?php\nconfig(['app.name' => 'x']);\nconfig()->string('app.name');\n"],
    'config read through the facade' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\Config;\nConfig::string('app.name');\nConfig::get('app.name');\n"],
    'a set of another class' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\Cache;\nCache::set('a', 'b');\n\$collection->set('a', 'b');\n"],
    'setTestNow in a unit test' => ['tests/Unit/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\Date;\nDate::setTestNow('2025-01-02');\n"],
    'the clock helpers of the test case' => ['tests/Feature/FooTest.php', "<?php\n\$this->travelTo(now());\ntest()->freezeTime();\n"],
    'app()->instance' => ['tests/Feature/FooTest.php', "<?php\napp()->instance(Foo::class, \$foo);\n"],
    'app()->bind of a fake that throws' => ['tests/Feature/FooTest.php', "<?php\napp()->bind(Foo::class, static fn (): never => throw new RuntimeException('down'));\n"],
    'app()->bind of a fake built per resolve' => ['tests/Feature/FooTest.php', "<?php\napp()->bind(Foo::class, fn () => new Foo());\n"],
    'app()->singleton of a fake built per resolve' => ['tests/Feature/FooTest.php', "<?php\napp()->singleton(Foo::class, fn () => new Foo());\n"],
    'App::bind of a fake that throws' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\App;\nApp::bind(Foo::class, static fn (): never => throw new RuntimeException('down'));\n"],
    'a binding on a provider' => ['tests/Support/Foo.php', "<?php\n\$provider->register();\n\$this->mock(Foo::class);\n"],
    'expect with a value' => ['tests/Feature/FooTest.php', "<?php\nexpect(\$value)->toBeTrue();\nexpect(\$value === true)->toBeTrue();\n"],
    'throwsNoExceptions' => ['tests/Feature/FooTest.php', "<?php\nit('works', fn () => \$run())->throwsNoExceptions();\n"],
    'toThrow with the class' => ['tests/Feature/FooTest.php', "<?php\nexpect(\$run)->toThrow(FooException::class, 'message');\n"],
    'toThrow with a function that asserts inside' => ['tests/Feature/FooTest.php', "<?php\nexpect(\$run)->toThrow(function (FooException \$e): void { expect(\$e->reason)->toBe('x'); });\n"],
    'toThrow with an arrow function that asserts inside' => ['tests/Feature/FooTest.php', "<?php\nexpect(\$run)->toThrow(fn (FooException \$e) => expect(\$e->reason)->toBe('x'));\n"],
    'toThrow with a function that has a bool closure inside' => ['tests/Feature/FooTest.php', "<?php\nexpect(\$run)->toThrow(function (FooException \$e) use (\$all): void { expect(\$all->every(fn (int \$i): bool => \$i > 0))->toBeTrue(); });\n"],
    'only on a collection, a request and a model' => ['tests/Feature/FooTest.php', "<?php\n\$collection->only(['a']);\ncollect(\$rows)->only('a');\n\$request->only('a');\nUser::query()->first()->only(['id']);\nArr::only(\$row, ['a']);\n"],
    'withoutMiddleware on a route' => ['tests/Feature/FooTest.php', "<?php\nuse Illuminate\\Support\\Facades\\Route;\nRoute::get('/x', FooController::class)->withoutMiddleware('auth');\n\$route('a')->withoutMiddleware('auth');\n\$registered->withoutMiddleware('auth');\n"],
    'exact expectations' => ['tests/Feature/FooTest.php', "<?php\nexpect(\$value)->toBeTrue()->and(\$other)->toBeFalse();\n\$this->get('/x')->assertServerError();\n"],
    'a test with its own subject' => ['tests/Feature/FooTest.php', "<?php\nit('signs a user in', function (): void {\n    expect(\$a)->toBe(1);\n});\nit('sorts the items', function (): void {\n    expect(\$a)->toBe(1);\n});\n"],
    'test() with no arguments, the current test case' => ['tests/Support/Foo.php', "<?php\ntest()->freezeTime();\n"],
    'the stray guards switched on, a faked sleep and a method named like sleep' => ['tests/Feature/FooTest.php', "<?php\nHttp::preventStrayRequests();\nProcess::preventStrayProcesses(true);\nSleep::fake();\nSleep::for(1)->second();\n\$worker->sleep(1);\nHttp::preventingStrayRequests();\n"],
    'a preset without ignoring' => ['tests/Unit/FooTest.php', "<?php\narch()->preset()->php();\n\$collection->ignoringCase();\n"],
    'hooks with a return type or an arrow function' => ['tests/Feature/FooTest.php', "<?php\nbeforeEach(function (): void {\n    config(['app.name' => 'x']);\n});\nafterEach(fn () => TemporaryPaths::delete());\n"],
    'a closure that is not a test' => ['tests/Feature/FooTest.php', "<?php\ncollect(\$rows)->each(function (\$row) {\n    return \$row;\n});\nexpect(function () {\n    return 1;\n})->toThrow(FooException::class);\n"],
    'assertStatus with a code that has no named assertion' => ['tests/Feature/FooTest.php', "<?php\n\$response->assertStatus(502);\n\$response->assertStatus(\$status);\n\$response->assertOk();\n"],
    'a set on a container binding that is not the config' => ['tests/Feature/FooTest.php', "<?php\napp('cache')->set('a', 'b');\n\$this->app['files']->set('a');\nexpect(\$flag)->toBeTrue();\n"],
    'banned calls in a string' => ['tests/Feature/FooTest.php', "<?php\n\$source = 'Config::set(\"a\", \"b\"); \$this->app->bind(Foo::class, fn () => \$foo);';\n"],
]);

it('allows the spelling the tests are written in', function (string $path, string $source): void {
    $root = SourceSandbox::create([$path => $source]);

    try {
        expect(new BannedTestCallGuard(new PhpFileInspector(), $root, 'tests', ['tests/Unit'])->violations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('allowed test calls');

it('filters on the path relative to the root when the checkout sits under a dot directory', function (): void {
    $root = SourceSandbox::create([
        'tests/Feature/FooTest.php' => "<?php\nuse Illuminate\\Support\\Facades\\Date;\nDate::setTestNow('2026-01-01');\n",
        'tests/Unit/BarTest.php' => "<?php\nuse Illuminate\\Support\\Facades\\Date;\nDate::setTestNow('2026-01-01');\n",
    ], prefix: '.checkout-');

    try {
        $violations = new BannedTestCallGuard(new PhpFileInspector(), $root, 'tests', ['tests/Unit'])->violations();

        expect(array_keys($violations))->toBe(['tests/Feature/FooTest.php']);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('keeps every banned call out of the test suite', function (): void {
    expect(new BannedTestCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'tests', ['tests/Unit'])->violations())->toBe([]);
});
