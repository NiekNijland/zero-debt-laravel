<?php

declare(strict_types=1);

use Tests\Support\Architecture\ControllerNameGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

arch('controllers are used by nothing')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

arch('every controller is invokable')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toHaveMethod('__invoke');

dataset('controller names that fail', [
    'in the controller root' => ['App\Http\Controllers\ShowController', 'a controller sits in the controller root'],
    'without the suffix' => ['App\Http\Controllers\Api\Order\Show', 'a controller is named <Verb>Controller'],
    'a verb that is not on the list' => ['App\Http\Controllers\Api\Order\FooController', 'the verb Foo is not one of Show, Store'],
]);

it('detects a controller that is not named <Verb>Controller in a folder of what it acts on', function (string $type, string $words): void {
    $guard = new ControllerNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers', ['Show', 'Store'], 'App\Http\Controllers');

    expect($guard->inType($type))->toContain($words);
})->with('controller names that fail');

it('accepts a verb controller in a folder, and names a verb that no controller carries and a folder with no controller', function (): void {
    $guard = new ControllerNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app/Http/Controllers', ['Show', 'Store'], 'App\Http\Controllers');
    $root = SourceSandbox::create([
        'c/Api/Order/ShowController.php' => "<?php\nnamespace App\\Http\\Controllers\\Api\\Order;\nfinal class ShowController {}\n",
        'empty/.gitkeep' => '',
    ], prefix: '.checkout-');

    try {
        $stale = new ControllerNameGuard(new PhpFileInspector(), $root, 'c', ['Show', 'Store'], 'App\Http\Controllers')->violations();
        $empty = new ControllerNameGuard(new PhpFileInspector(), $root, 'empty', ['Show'], 'App\Http\Controllers')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($guard->inType('App\Http\Controllers\Api\Order\ShowController'))->toBeNull()
        ->and($stale)->toBe(['the verb Store is on the list and no controller carries it: remove it from the list'])
        ->and($empty[0])->toContain('no controller was read');
});

it('reads the verbs from the table of the guide and stops the table at a blank line', function (): void {
    $guide = "| Verb | Used for |\n| --- | --- |\n| `Index` | a list |\n| `Show` | one record |\n\n| `Other` | not in the table |\n";

    expect(ControllerNameGuard::verbsOf($guide))->toBe(['Index', 'Show'])
        ->and(ControllerNameGuard::verbsOf("no table here\n"))->toBe([]);
});

it('names every controller <Verb>Controller in a folder named after what it acts on, with the verbs of app/AGENTS.md', function (): void {
    $root = dirname(__DIR__, 2);
    $verbs = ControllerNameGuard::verbsOf((string) file_get_contents($root . '/app/AGENTS.md'));

    expect($verbs)->toContain('Show')
        ->and(new ControllerNameGuard(new PhpFileInspector(), $root, 'app/Http/Controllers', $verbs, 'App\Http\Controllers')->violations())->toBe([]);
});
