<?php

declare(strict_types=1);

use Tests\Support\Architecture\PestBindingReader;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('Pest bindings', [
    'extend with a class constant and a directory' => ["<?php\npest()->extend(Base::class)->in('Feature');\n", [['bound' => ['Base'], 'targets' => ['Feature']]]],
    'uses with two classes and a directory list' => ["<?php\nuses(One::class, Two::class)->in('Feature', 'Unit');\n", [['bound' => ['One', 'Two'], 'targets' => ['Feature', 'Unit']]]],
    'a class named by a string' => ["<?php\npest()->extend('App\\\\Base')->in('Unit');\n", [['bound' => ['App\\Base'], 'targets' => ['Unit']]]],
    'no directory' => ["<?php\npest()->extend(Base::class);\n", [['bound' => ['Base'], 'targets' => null]]],
    'a directory constant joined to a path' => ["<?php\npest()->use(Base::class)->in(__DIR__ . '/Unit');\n", [['bound' => ['Base'], 'targets' => ['/Unit']]]],
    'a directory that is not a literal' => ["<?php\npest()->extend(Base::class)->in(\$folder);\n", [['bound' => ['Base'], 'targets' => ['*']]]],
]);

it('reads what a Pest call binds and where', function (string $source, array $expected): void {
    $root = SourceSandbox::create(['Pest.php' => $source]);

    try {
        expect(PestBindingReader::pestBindings($root . '/Pest.php'))->toBe($expected);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('Pest bindings');

it('reads no binding from calls that bind nothing', function (): void {
    $root = SourceSandbox::create(['Pest.php' => "<?php\nit('works', fn () => expect(1)->toBe(1));\n\$collection->in('a');\n"]);

    try {
        expect(PestBindingReader::pestBindings($root . '/Pest.php'))->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('reads the bindings of tests/Pest.php', function (): void {
    $bindings = PestBindingReader::pestBindings(__DIR__ . '/../Pest.php');

    expect($bindings)->not->toBeEmpty()
        ->and($bindings[0]['targets'])->toBe(['Feature']);
});
