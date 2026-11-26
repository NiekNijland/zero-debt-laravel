<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\QueuedClassLimitsGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$head = "<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\n";

dataset('queued classes without their limits', [
    'a job without either' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\nfinal class ExportJob implements ShouldQueue { public function handle(): void {} }\n", ['App\\ExportJob is queued without $timeout', 'App\\ExportJob is queued without $tries']],
    'a job without a timeout' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\nfinal class ExportJob implements ShouldQueue { public int \$tries = 3; }\n", ['App\\ExportJob is queued without $timeout']],
    'a job without tries' => ["<?php\nnamespace App;\nfinal class ExportJob implements \\Illuminate\\Contracts\\Queue\\ShouldQueue { public int \$timeout = 60; }\n", ['App\\ExportJob is queued without $tries']],
    'a notification under an alias of the contract' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue as Queued;\nclass ShippedNotification extends Notification implements Queued { public int \$tries = 1; public int \$backoff = 5; }\n", ['App\\ShippedNotification is queued without $timeout']],
    'a job that retries until a moment without a timeout' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\nfinal class ChargeJob implements ShouldQueue { public function retryUntil(): \\DateTimeInterface { return now()->addHour(); } }\n", ['App\\ChargeJob is queued without $timeout']],
    'a listener that is queued after the commit' => ["<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldQueueAfterCommit;\nfinal class StartExport implements ShouldQueueAfterCommit { public int \$tries = 5; public function handle(): void {} }\n", ['App\\StartExport is queued without $timeout']],
    'a listener that is queued after the commit without either' => ["<?php\nnamespace App;\nfinal readonly class StartExport implements \\Illuminate\\Contracts\\Queue\\ShouldQueueAfterCommit { public function handle(): void {} }\n", ['App\\StartExport is queued without $timeout', 'App\\StartExport is queued without $tries']],
    'a sub-interface of the contract' => [$head . "interface Background extends ShouldQueue {}\nfinal class ExportJob implements Background {}\n", ['App\\ExportJob is queued without $timeout', 'App\\ExportJob is queued without $tries']],
    'a parent in the tree that is queued' => [$head . "abstract class BaseJob implements ShouldQueue {}\nfinal class ExportJob extends BaseJob { public int \$tries = 3; }\n", ['App\\ExportJob is queued without $timeout']],
    'a trait that holds something else' => [$head . "trait Retries { public int \$backoff = 3; }\nfinal class ExportJob implements ShouldQueue { use Retries; }\n", ['App\\ExportJob is queued without $timeout', 'App\\ExportJob is queued without $tries']],
    'an anonymous queued class' => [$head . "\$job = new class implements ShouldQueue {};\n", ['an anonymous class on line 4 is queued without $timeout', 'an anonymous class on line 4 is queued without $tries']],
    'a class whose parent is queued' => ["<?php\nnamespace App;\nuse Illuminate\\Foundation\\Console\\QueuedCommand;\nfinal class NightlyCommand extends QueuedCommand {}\n", ['App\\NightlyCommand is queued without $timeout', 'App\\NightlyCommand is queued without $tries']],
]);

it('detects a queued class that does not declare its timeout or its tries', function (string $source, array $reasons): void {
    $violations = new QueuedClassLimitsGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'config/queue.php')->inSource($source);

    expect($violations)->toHaveCount(count($reasons));

    foreach ($reasons as $index => $reason) {
        expect($violations[$index])->toContain($reason)->toContain('declare public int');
    }
})->with('queued classes without their limits');

dataset('queued classes with their limits and near misses', [
    'both properties' => [$head . "final class ExportJob implements ShouldQueue { public int \$tries = 3; public int \$timeout = 60; }\n"],
    'a timeout and retryUntil()' => [$head . "final class ChargeJob implements ShouldQueue { public int \$timeout = 60; public function retryUntil(): \\DateTimeInterface { return now()->addHour(); } }\n"],
    'promoted constructor parameters' => [$head . "final class ExportJob implements ShouldQueue { public function __construct(public int \$timeout = 30, public int \$tries = 3) {} }\n"],
    'a trait that holds the limits' => [$head . "trait Limits { public int \$timeout = 30; public int \$tries = 3; }\nfinal class ExportJob implements ShouldQueue { use Limits; }\n"],
    'a parent in the tree that holds the limits' => [$head . "abstract class BaseJob implements ShouldQueue { public int \$timeout = 30; public int \$tries = 3; }\nfinal class ExportJob extends BaseJob {}\n"],
    'the attribute form' => [$head . "use Illuminate\\Queue\\Attributes\\Timeout;\nuse Illuminate\\Queue\\Attributes\\Tries;\n#[Tries(3)]\n#[Timeout(30)]\nfinal class ExportJob implements ShouldQueue {}\n"],
    'a listener queued after the commit with both' => [$head . "final class StartExport implements \\Illuminate\\Contracts\\Queue\\ShouldQueueAfterCommit { public int \$timeout = 60; public int \$tries = 5; }\n"],
    'an abstract base without limits, its children not queued' => [$head . "abstract class BaseJob implements ShouldQueue {}\n"],
    'a class that is not queued' => [$head . "final class PlainListener { public function handle(): void {} }\n"],
    'a class named after the contract in another namespace' => [$head . "final class OtherJob implements \\App\\Contracts\\ShouldQueue {}\n"],
]);

it('accepts a queued class with its limits however they are declared, and the near misses', function (string $source): void {
    expect(new QueuedClassLimitsGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'config/queue.php')->inSource($source))->toBe([]);
})->with('queued classes with their limits and near misses');

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create(['app/Job.php' => "<?php\nnamespace App;\nuse Illuminate\\Contracts\\Queue\\ShouldQueue;\nfinal class Job implements ShouldQueue {}\n", 'app/Clean.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new QueuedClassLimitsGuard(new PhpFileInspector(), $root, 'app', 'config/queue.php')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(2)
        ->and($violations[0])->toStartWith('app/Job.php: App\\Job is queued without $timeout');
});

it('gives every queued class in app its own timeout and tries', function (): void {
    expect(new QueuedClassLimitsGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'config/queue.php')->violations())->toBe([]);
});
