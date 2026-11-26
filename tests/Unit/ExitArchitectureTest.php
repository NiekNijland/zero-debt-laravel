<?php

declare(strict_types=1);

use Tests\Support\Architecture\ExitGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

it('detects exit and die in every spelling', function (string $source, string $construct): void {
    $violations = new ExitGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'config', 'database', 'routes', 'tests'])->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('3: ' . $construct . ' ends the process')
        ->and($violations[0])->toContain('return self::FAILURE');
})->with([
    'exit' => ["<?php\nnamespace App;\nexit;\n", 'exit'],
    'exit with a code' => ["<?php\nnamespace App;\nif (\$failed) { exit(1); }\n", 'exit'],
    'die' => ["<?php\nnamespace App;\ndie;\n", 'die'],
    'die with a message' => ["<?php\nnamespace App;\n\$ok or die('stopped');\n", 'die'],
    'the fully qualified function' => ["<?php\nnamespace App;\n\\exit(1);\n", 'exit'],
    'inside a closure in a config file' => ["<?php\nreturn ['guard' => static function (): void {\n    exit(2);\n}];\n", 'exit'],
]);

it('accepts a return, an exception, an exit code and the word in text', function (): void {
    $source = <<<'PHP'
        <?php
        namespace App;
        final class ImportProductsCommand
        {
            public function handle(): int
            {
                if ($this->failed) { return self::FAILURE; }
                $this->line('exit');
                $process->exit(1);
                Exit_::KIND_DIE;
                throw new StoppedException('die');
            }
        }
        PHP;

    expect(new ExitGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'config', 'database', 'routes', 'tests'])->inSource($source))->toBe([]);
});

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create(['app/Ends.php' => "<?php\nexit(1);\n", 'app/Clean.php' => "<?php\n"], prefix: '.checkout-');

    try {
        $violations = new ExitGuard(new PhpFileInspector(), $root, ['app'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Ends.php:2: exit ends the process');
});

it('never ends the process with exit or die in app, bootstrap, config, database, routes and tests', function (): void {
    expect(new ExitGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'bootstrap', 'config', 'database', 'routes', 'tests'])->violations())->toBe([]);
});
