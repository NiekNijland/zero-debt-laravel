<?php

declare(strict_types=1);

use Tests\Support\Architecture\ScheduledTaskGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('scheduled tasks that may overlap', [
    'no lock' => ["<?php\nuse Illuminate\\Support\\Facades\\Schedule;\nSchedule::command('carts:prune')->hourly()->onOneServer();\n", 3],
    'a lock without its minutes' => ["<?php\nuse Illuminate\\Support\\Facades\\Schedule;\n\nSchedule::command('carts:prune')->daily()->onOneServer()->withoutOverlapping();\n", 4],
    'a job on the global alias' => ["<?php\n\\Schedule::job(new \\App\\Jobs\\PruneCartsJob())\n    ->everyMinute()\n    ->onOneServer();\n", 2],
    'a task on the scheduler parameter of withSchedule()' => ["<?php\nuse Illuminate\\Console\\Scheduling\\Schedule;\n\$app->withSchedule(function (Schedule \$schedule): void {\n    \$schedule->command('carts:prune')->daily();\n});\n", 4],
    'a pending task held in a variable' => ["<?php\nuse Illuminate\\Support\\Facades\\Schedule;\n\$task = Schedule::command('carts:prune');\n\$task->daily();\n", 3],
]);

it('detects a scheduled task without a lock against overlapping runs', function (string $source, int $line): void {
    $violations = new ScheduledTaskGuard(dirname(__DIR__, 2), ['routes/*.php', 'bootstrap/app.php'])->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($line . ': a scheduled task without ->withoutOverlapping(<minutes>)')
        ->and($violations[0])->toContain('add it with the minutes');
})->with('scheduled tasks that may overlap');

it('accepts a scheduled task with its minutes anywhere in the chain and a statement that schedules nothing', function (): void {
    $source = <<<'PHP'
        <?php
        use Illuminate\Console\Scheduling\Schedule;
        use Illuminate\Support\Facades\Artisan;
        use Illuminate\Support\Facades\Schedule as Facade;
        Facade::command('carts:prune')->hourly()->onOneServer()->withoutOverlapping(30);
        Facade::command('invoices:send')
            ->dailyAt('03:00')
            ->withoutOverlapping(360)
            ->when(static fn (): bool => true);
        Facade::command('reports:build')->daily()->withoutOverlapping(config('schedule.minutes'));
        $app->withSchedule(function (Schedule $schedule): void {
            $schedule->command('a')->daily()->withoutOverlapping(10);
        });
        $task = Facade::command('b');
        $task->daily()->withoutOverlapping(10);
        Artisan::command('inspire', static fn () => null)->hourly();
        $newsletter->schedule()->daily();
        $other->command('c')->daily();
        PHP;

    expect(new ScheduledTaskGuard(dirname(__DIR__, 2), ['routes/*.php', 'bootstrap/app.php'])->inSource($source))->toBe([]);
});

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create(['routes/console.php' => "<?php\nSchedule::command('x')->daily();\n", 'routes/clean.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new ScheduledTaskGuard($root, ['routes/*.php'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('routes/console.php:2: a scheduled task without ->withoutOverlapping(<minutes>)');
});

it('gives every scheduled task in routes and the bootstrap file its lock against overlapping runs', function (): void {
    expect(new ScheduledTaskGuard(dirname(__DIR__, 2), ['routes/*.php', 'bootstrap/app.php'])->violations())->toBe([]);
});
