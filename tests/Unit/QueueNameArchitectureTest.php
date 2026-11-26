<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\QueueNameGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('queue names written as text', [
    'onQueue in a constructor' => ["<?php\nnamespace App;\nfinal class ExportJob { public function __construct() { \$this->onQueue('exports'); } }\n", 'onQueue() with a queue name as text'],
    'onQueue at dispatch' => ["<?php\nnamespace App;\nExportJob::dispatch(\$id)->onQueue('default');\n", 'onQueue() with a queue name as text'],
    'onQueue on a notification' => ["<?php\nnamespace App;\n\$user?->notification()?->onQueue(\"exports\");\n", 'onQueue() with a queue name as text'],
    'allOnQueue on a chain' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Bus;\nBus::chain(\$jobs)->allOnQueue('exports')->dispatch();\n", 'allOnQueue() with a queue name as text'],
    'the queue property' => ["<?php\nnamespace App;\nfinal class ExportJob { public \$queue = 'exports'; }\n", '$queue with a queue name as text'],
    'the queue property set by hand' => ["<?php\nnamespace App;\nfinal class ExportJob { public function __construct() { \$this->queue = 'mail'; } }\n", '->queue set to a queue name as text'],
    'a name joined to a variable' => ["<?php\nnamespace App;\n\$job->onQueue('exports-' . \$tenant);\n", 'onQueue() with a queue name as text'],
    'a name in an interpolated string' => ["<?php\nnamespace App;\n\$job->onQueue(\"exports-{\$tenant}\");\n", 'onQueue() with a queue name as text'],
    'a choice between two texts' => ["<?php\nnamespace App;\n\$job->onQueue(\$urgent ? 'fast' : 'slow');\n", 'onQueue() with a queue name as text'],
    'a coalesce with a text' => ["<?php\nnamespace App;\n\$job->onQueue(\$name ?? 'default');\n", 'onQueue() with a queue name as text'],
    'a match arm with a text' => ["<?php\nnamespace App;\n\$job->onQueue(match (\$kind) { 'a' => Queue::A->value, default => 'b' });\n", 'onQueue() with a queue name as text'],
    'a promoted queue property' => ["<?php\nnamespace App;\nfinal class ExportJob { public function __construct(public string \$queue = 'exports') {} }\n", 'a promoted $queue with a queue name as text'],
    'pushOn on the queue facade' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Queue;\nQueue::pushOn('exports', new ExportJob());\n", 'pushOn() with a queue name as text'],
    'laterOn on the queue facade' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Queue;\nQueue::laterOn('exports', 60, new ExportJob());\n", 'laterOn() with a queue name as text'],
    'a value in viaQueues that is a choice' => ["<?php\nnamespace App;\nfinal class DoneNotification { public function viaQueues(): array { return ['mail' => \$x ? 'a' : 'b']; } }\n", 'viaQueues() with a queue name as text'],
    'viaQueues of a notification' => ["<?php\nnamespace App;\nfinal class DoneNotification { public function viaQueues(): array { return ['mail' => 'default']; } }\n", 'viaQueues() with a queue name as text'],
]);

it('detects a queue that is named with text', function (string $source, string $reason): void {
    $violations = new QueueNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'config/queue.php', 'the queue enum')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($reason)
        ->and($violations[0])->toContain('Queue::X', 'config/queue.php', 'the queue enum');
})->with('queue names written as text');

it('accepts a queue named by the enum and a variable that happens to be called queue', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App;
        use App\Modules\Observability\Enums\Queue;
        final class ExportJob
        {
            public function __construct(Queue $queue)
            {
                $this->onQueue(Queue::EXPORTS);
                $this->onQueue(Queue::EXPORTS->value);
                $this->onQueue($queue);
                $this->onQueue($enum ?? Queue::DEFAULT);
                $this->onQueue(config('queue.name'));
                Queue::pushOn(Queue::EXPORTS, new ExportJob());
            }
            public function viaQueues(): array { return ['mail' => Queue::DEFAULT->value]; }
            public function walk(): void { $queue = ['a']; $label = 'default'; $this->line("Jobs waiting on {$label}"); }
        }
        PHP;

    expect(new QueueNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'config/queue.php', 'the queue enum')->inSource($source))->toBe([]);
});

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create(['app/Pushes.php' => "<?php\n\$job->onQueue('exports');\n", 'app/Clean.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new QueueNameGuard(new PhpFileInspector(), $root, 'app', 'config/queue.php', 'the queue enum')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Pushes.php:2: onQueue() with a queue name as text');
});

it('names every queue in app with a case of the Queue enum', function (): void {
    expect(new QueueNameGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app', 'config/queue.php', 'the queue enum')->violations())->toBe([]);
});
