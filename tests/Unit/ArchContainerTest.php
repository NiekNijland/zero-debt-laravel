<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Tests\Support\Architecture\ContainerBindingGuard;
use Tests\Support\Architecture\ContainerCallGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RouteFileLoadGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

arch('routes are registered only in routes/ and bootstrap/app.php')
    ->expect([Route::class, Router::class])
    ->not->toBeUsedIn(['App', 'Database']);

dataset('route file loads', [
    'in a provider method' => ["<?php\nfinal class P { public function boot(): void { \$this->loadRoutesFrom(__DIR__ . '/x.php'); } }\n"],
    'on a held object' => ["<?php\n\$provider->loadRoutesFrom(\$path);\n"],
]);

it('detects a class that loads a route file', function (string $source): void {
    $violations = new RouteFileLoadGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('register routes in the route files and in the bootstrap file');
})->with('route file loads');

it('accepts a class that mentions the call in a string or a comment, or loads something else', function (): void {
    $guard = new RouteFileLoadGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app');

    expect($guard->inSource("<?php\n// loadRoutesFrom(\$a)\n\$a = 'loadRoutesFrom';\n\$this->loadMigrationsFrom(\$path);\n"))->toBe([]);
});

it('reads the folder below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/P.php' => "<?php\n\$this->loadRoutesFrom(\$p);\n", 'app/Q.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new RouteFileLoadGuard(new PhpFileInspector(), $root, 'app')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/P.php: line 2: a class loads a route file');
});

it('loads no route file from a class under app/', function (): void {
    expect(new RouteFileLoadGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});

dataset('container calls', [
    'resolve()' => ['<?php $a = resolve(Foo::class);', 1],
    'app() and a global app()' => ['<?php $a = app(Foo::class); $b = \app();', 2],
    'the facade make and a qualified call' => ['<?php $a = App::make(Foo::class); $b = ' . App::class . '::call($x);', 2],
    'a held container' => ['<?php $a = app()->make(Foo::class); $b = $this->container->make(Foo::class); $c = $container->make(Foo::class);', 3],
    'the container instance' => ['<?php $a = Container::getInstance();', 1],
    'in a trait' => ['<?php trait T { public function f(): void { resolve(Foo::class); } }', 1],
    'in the failed hook of a job' => ['<?php final class J { public function failed(): void { app(Foo::class)->handle(); } }', 1],
    'in a closure of a boot hook' => ['<?php final class M extends Model { protected static function booted(): void { static::saved(static fn () => resolve(Foo::class)->forget()); } }', 1],
]);

it('detects a dependency that comes through the container', function (string $source, int $count): void {
    $violations = new ContainerCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', [], App::class)->inSource($source);

    expect($violations)->toHaveCount($count)
        ->and($violations[0])->toContain('take the dependency as a constructor argument, or as an argument of handle()');
})->with('container calls');

it('names the method of a container call and accepts a facade method that is not a call into the container', function (): void {
    $guard = new ContainerCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', [], App::class);

    expect($guard->inSource('<?php final class J { public function failed(): void { app(Foo::class); } }')[0])->toStartWith('failed line 1: ')
        ->and($guard->inSource('<?php $a = app();')[0])->toStartWith('outside a method line 1: ')
        ->and($guard->inSource('<?php $a = App::environment(); $b = App::getLocale(); $c = Hash::make($x); $d = $this->resolve($x); $e = $factory->make();'))->toBe([]);
});

it('skips the folders that wire the container and reads a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['app/Providers/P.php' => "<?php\n\$a = app(X::class);\n", 'app/Service.php' => "<?php\n\$a = app(X::class);\n"], prefix: '.checkout-');

    try {
        $violations = new ContainerCallGuard(new PhpFileInspector(), $root, 'app', ['app/Providers/'], App::class)->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Service.php: outside a method line 2');
});

it('takes every dependency through the constructor or handle(), never through the container', function (): void {
    expect(new ContainerCallGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', ['app/Providers/'], App::class)->violations())->toBe([]);
});

dataset('bindings that a class declares itself', [
    'a scoped class of the application' => ["use App\\Modules\\Orders\\Services\\X;\n\$this->app->scoped(X::class);", 'App\\Modules\\Orders\\Services\\X'],
    'an interface of the application bound to a class' => ["use App\\Modules\\Orders\\Contracts\\I;\nuse App\\Modules\\Orders\\Services\\C;\n\$this->app->bind(I::class, C::class);", 'App\\Modules\\Orders\\Contracts\\I'],
    'a singleton under an alias' => ["use App\\Support\\Clock as AppClock;\n\$this->app->singleton(AppClock::class);", 'App\\Support\\Clock'],
]);

it('detects a binding of an application class in a provider', function (string $source, string $class): void {
    $violations = new ContainerBindingGuard(dirname(__DIR__, 2), 'app/Providers', 'App\\')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('binds ' . $class . ' in a provider', '#[Scoped], #[Singleton] or #[Bind]');
})->with('bindings that a class declares itself');

it('accepts the binding of a vendor class and of a closure', function (): void {
    $guard = new ContainerBindingGuard(dirname(__DIR__, 2), 'app/Providers', 'App\\');
    $source = "use Psr\\Clock\\ClockInterface;\nuse App\\Support\\SystemClock;\n\$this->app->singleton(ClockInterface::class, SystemClock::class);\n\$this->app->bind(App\\Modules\\Shipments\\Contracts\\LabelPrinter::class, static fn () => 1);";

    expect($guard->inSource($source))->toBe([]);
});

it('reads the providers below a checkout under a dot directory and fails on a folder without one', function (): void {
    $root = SourceSandbox::create(['providers/P.php' => "<?php\nuse App\\X;\n\$this->app->scoped(X::class);\n", 'empty/.gitkeep' => ''], prefix: '.checkout-');

    try {
        $violations = new ContainerBindingGuard($root, 'providers', 'App\\')->violations();
        $empty = new ContainerBindingGuard($root, 'empty', 'App\\')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('providers/P.php: binds App\\X in a provider')
        ->and($empty[0])->toContain('no provider was read');
});

it('lets a class of the application declare its own binding, not a provider', function (): void {
    expect(new ContainerBindingGuard(dirname(__DIR__, 2), 'app/Providers', 'App\\')->violations())->toBe([]);
});
