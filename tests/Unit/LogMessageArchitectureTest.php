<?php

declare(strict_types=1);

use Tests\Support\Architecture\LogMessageGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('log lines built from the message of an exception', [
    'the message itself' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\ntry { f(); } catch (RefusedException \$e) { Log::info(\$e->getMessage()); }\n"],
    'a concatenated message' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::warning('Export failed: ' . \$exception->getMessage(), ['order_id' => 1]);\n"],
    'an interpolated message' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::error(\"Export failed: {\$e->getMessage()}\");\n"],
    'sprintf' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::notice(sprintf('Export failed: %s', \$e->getMessage()));\n"],
    'the previous exception' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::debug('Export failed: ' . \$e->getPrevious()?->getMessage());\n"],
    'a channel' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::channel('daily')->info(\$e->getMessage());\n"],
    'the global alias' => ["<?php\nnamespace App;\n\\Log::info(\$e->getMessage());\n"],
    'Log::log with a level' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::log('info', \$e->getMessage());\n"],
    'the logger helper' => ["<?php\nnamespace App;\nlogger()->info(\$e->getMessage());\n"],
    'the logger helper with a message' => ["<?php\nnamespace App;\nlogger('Export failed: ' . \$e->getMessage());\n"],
    'an injected logger' => ["<?php\nnamespace App;\nuse Psr\\Log\\LoggerInterface;\nfinal class X { public function __construct(private LoggerInterface \$logger) {} public function f(\$e): void { \$this->logger->warning(\$e->getMessage()); } }\n"],
    'a logger received as a parameter' => ["<?php\nnamespace App;\nuse Psr\\Log\\LoggerInterface;\nfinal class X { public function f(LoggerInterface \$logger, \$e): void { \$logger->error('x: ' . \$e->getMessage()); } }\n"],
    'the container string' => ["<?php\nnamespace App;\napp('log')->warning(\$e->getMessage());\n"],
    'a chain of several calls from the facade' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::channel('a')->withContext(['k' => 1])->warning(\$e->getMessage());\n"],
    'a message held in a variable' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\n\$message = \$e->getMessage();\nLog::warning(\$message);\n"],
    'a message held in a variable and put in the context' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\n\$text = 'x: ' . \$e->getMessage();\nLog::warning('Export failed', ['error' => \$text]);\n"],
    'a context key' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::warning('Export failed', ['order_id' => 1, 'message' => \$e->getMessage()]);\n"],
    'a context key of a nullable exception' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::warning('Export failed', ['error' => \$e?->getMessage()]);\n"],
    'a nested context key' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::channel('daily')->info('Export failed', ['cause' => ['text' => 'x: ' . \$e->getMessage()]]);\n"],
    'the context of Log::log' => ["<?php\nnamespace App;\nuse Illuminate\\Support\\Facades\\Log;\nLog::log('info', 'Export failed', ['error' => \$e->getMessage()]);\n"],
    'the context of the logger helper' => ["<?php\nnamespace App;\nlogger()->warning('Export failed', ['error' => \$e->getMessage()]);\n"],
]);

it('detects a log line whose message or context is built from getMessage()', function (string $source): void {
    $violations = new LogMessageGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain("['exception' => \$e]");
})->with('log lines built from the message of an exception');

it('accepts a fixed line with the exception as context, a console line and a response that carries the message', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Support\Facades\Log;
        try { f(); } catch (RefusedException $e) { Log::info('Label refused', ['exception' => $e]); }
        Log::info('Label refused', ['order_id' => 1]);
        Log::channel('daily')->debug('Export retried', ['exception' => $e]);
        $this->error('Export failed: ' . $e->getMessage());
        $output->info($e->getMessage());
        throw new DomainException($e->getMessage(), previous: $e);
        report($e);
        Log::info(...);
        $failure = ['message' => $e->getMessage()];
        $this->logger->info('fixed line');
        $this->untyped->warning($e->getMessage());
        Log::channel('a')->withContext(['k' => 1])->warning('what failed', ['exception' => $e]);
        $count = count($rows);
        Log::info($count);
        PHP;

    expect(new LogMessageGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source))->toBe([]);
});

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create(['app/Logs.php' => "<?php\nuse Illuminate\\Support\\Facades\\Log;\nLog::error(\$e->getMessage());\n", 'app/Clean.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new LogMessageGuard(new PhpFileInspector(), $root, 'app')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Logs.php:3: the log message is built from getMessage()');
});

it('never builds a log message or its context in app from the message of an exception', function (): void {
    expect(new LogMessageGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
