<?php

declare(strict_types=1);

use Tests\Support\Architecture\ScheduledOneServerGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('scheduled tasks that fail', [
    'a command without one server' => ['<?php Schedule::command("a")->daily();', 'line 1: a scheduled task does not run on one server: end it with ->onOneServer()'],
    'a closure' => ['<?php Schedule::call(static function (): void { run(); })->hourly()->onOneServer();', 'a closure is scheduled: make it a command'],
    'the second of two' => ['<?php Schedule::command("a")->onOneServer(); Schedule::command("b")->daily();', 'does not run on one server'],
]);

it('detects a scheduled task that runs on every server and a closure', function (string $source, string $words): void {
    $violations = new ScheduledOneServerGuard(dirname(__DIR__, 2), 'routes')->inSource($source);

    expect($violations)->not->toBe([])
        ->and($violations[0])->toContain($words);
})->with('scheduled tasks that fail');

it('accepts a task that ends in onOneServer, in any place of the chain', function (): void {
    $guard = new ScheduledOneServerGuard(dirname(__DIR__, 2), 'routes');

    expect($guard->inSource('<?php Schedule::command("b")->onOneServer()->daily();'))->toBe([])
        ->and($guard->inSource('<?php Schedule::command("b")->daily()->onOneServer();'))->toBe([])
        ->and($guard->inSource('<?php $schedule->call(fn () => 1); Other::call(fn () => 2);'))->toBe([]);
});

it('reads the route files below a checkout under a dot directory', function (): void {
    $root = SourceSandbox::create(['routes/console.php' => "<?php\nSchedule::command('a')->daily();\n"], prefix: '.checkout-');

    try {
        $violations = new ScheduledOneServerGuard($root, 'routes')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('routes/console.php: line 2: a scheduled task does not run on one server');
});

it('passes a schedule with no task and fails on a folder that does not exist or holds no file', function (): void {
    $root = SourceSandbox::create(['routes/console.php' => "<?php\n// nothing is scheduled yet\n", 'empty/.gitkeep' => ''], prefix: '.checkout-');

    try {
        $none = new ScheduledOneServerGuard($root, 'routes')->violations();
        $missing = fn (): array => new ScheduledOneServerGuard($root, 'renamed')->violations();
        $empty = fn (): array => new ScheduledOneServerGuard($root, 'empty')->violations();

        expect($none)->toBe([])
            ->and($missing)->toThrow(InvalidArgumentException::class, 'does not exist')
            ->and($empty)->toThrow(InvalidArgumentException::class, 'holds no PHP file');
    } finally {
        SourceSandbox::remove($root);
    }
});

it('runs every scheduled task as a command on one server', function (): void {
    expect(new ScheduledOneServerGuard(dirname(__DIR__, 2), 'routes')->violations())->toBe([]);
});
