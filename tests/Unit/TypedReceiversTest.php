<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\Dispatcher;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\NodeFinder;
use Tests\Support\Architecture\PhpSource;
use Tests\Support\Architecture\TemporarySource;
use Tests\Support\Architecture\TypedReceivers;

uses()->group('arch');

dataset('receivers typed as a dispatcher', [
    'a typed parameter' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Events\\Dispatcher;\nfunction f(Dispatcher \$events) { \$events->dispatch(1); }\n", true],
    'a promoted property' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Events\\Dispatcher;\nfinal class A { public function __construct(private Dispatcher \$events) {} public function f() { \$this->events->dispatch(1); } }\n", true],
    'a declared property' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Events\\Dispatcher;\nfinal class A { private Dispatcher \$events; public function f() { \$this->events->dispatch(1); } }\n", true],
    'the container string' => ["<?php\napp('events')->dispatch(1);\n", true],
    'the container with the class' => ["<?php\nuse Illuminate\\Contracts\\Events\\Dispatcher;\napp(Dispatcher::class)->dispatch(1);\n", true],
    'an untyped receiver' => ["<?php\nfunction f(\$events) { \$events->dispatch(1); }\n", false],
    'a receiver of another type' => ["<?php\nnamespace App;\nuse Illuminate\\Log\\LogManager;\nfunction f(LogManager \$events) { \$events->dispatch(1); }\n", false],
    'the container with another key' => ["<?php\napp('log')->dispatch(1);\n", false],
]);

it('knows a receiver that holds an object of a type by declaration', function (string $source, bool $expected): void {
    $held = TemporarySource::with($source, static function (string $path): bool {
        $statements = PhpSource::statements($path);
        $receivers = TypedReceivers::in($statements, [Dispatcher::class], ['events']);
        $call = new NodeFinder()->findFirstInstanceOf($statements, MethodCall::class);

        return $call instanceof MethodCall && $receivers->holds($call->var);
    });

    expect($held)->toBe($expected);
})->with('receivers typed as a dispatcher');

it('does not hold a receiver that is no variable, property or container call', function (): void {
    $held = TemporarySource::with("<?php\nmake()->dispatch(1);\n", static function (string $path): bool {
        $call = new NodeFinder()->findFirstInstanceOf(PhpSource::statements($path), MethodCall::class);

        return $call instanceof MethodCall && TypedReceivers::in([], [Dispatcher::class])->holds($call->var);
    });

    expect($held)->toBeFalse();
});
