<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Tests\Support\Architecture\ConfigAccessGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('untyped config reads', [
    'the helper' => ['<?php $a = config(\'app.name\');'],
    'the helper with a default and a backslash' => ['<?php $a = \config(\'app.name\', \'x\');'],
    'the helper without a key' => ['<?php $a = config()->string(\'app.name\');'],
    'the facade get' => ['<?php $a = Config::get(\'app.name\');'],
    'the qualified facade get' => ['<?php $a = ' . Config::class . "::get('app.name');"],
    'the injected repository get' => ['<?php $a = $this->config->get(\'app.name\');'],
]);

it('detects a config value that is read without a type', function (string $source): void {
    $messages = new ConfigAccessGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'], Config::class)->inSource($source);

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain('through a typed accessor');
})->with('untyped config reads');

dataset('typed config reads and near misses', [
    'the typed facade accessor' => ['<?php $a = Config::string(\'app.name\');'],
    'a set and a typed read on the repository' => ['<?php Config::set(\'app.name\', \'x\'); $a = $this->config->string(\'app.name\');'],
    'a get on another receiver and a call in a comment' => ["<?php \$a = \$config->get('app.name'); \x2f\x2f config('app.name')"],
    'a function with the same name in a namespace' => ['<?php namespace X; $a = \X\config(\'app.name\');'],
]);

it('accepts a typed config read and the near misses', function (string $source): void {
    expect(new ConfigAccessGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'], Config::class)->inSource($source))->toBe([]);
})->with('typed config reads and near misses');

it('reads config through the typed accessors', function (): void {
    expect(new ConfigAccessGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'routes', 'config', 'database'], Config::class)->violations())->toBe([]);
});

it('reads the folders below a checkout under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create(['app/Bad.php' => "<?php\n\$a = config('app.name');\n", 'app/Good.php' => "<?php\n\$a = Config::string('app.name');\n"], prefix: '.checkout-');

    try {
        $violations = new ConfigAccessGuard(new PhpFileInspector(), $root, ['app'], Config::class)->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Bad.php: line 2: config is read untyped');
});
