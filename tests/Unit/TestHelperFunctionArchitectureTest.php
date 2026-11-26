<?php

declare(strict_types=1);

use Tests\Support\Architecture\TestHelperFunctionGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('functions declared in a test file', [
    'top level function' => ["<?php\nfunction helper(): void {}\n", ['helper']],
    'function returning by reference' => ["<?php\nfunction &helper(): array { static \$rows = []; return \$rows; }\n", ['helper']],
    'function behind function_exists' => ["<?php\nif (! function_exists('helper')) {\n    function helper(): void {}\n}\n", ['helper']],
    'function inside a describe closure' => ["<?php\ndescribe('group', function (): void {\n    function helper(): void {}\n});\n", ['helper']],
    'function inside a method body' => ["<?php\nfinal class Foo\n{\n    public function run(): void\n    {\n        function nested(): void {}\n    }\n}\n", ['nested']],
    'function in a namespace' => ["<?php\nnamespace Tests\\Feature;\nfunction helper(): void {}\n", ['helper']],
    'function after a class' => ["<?php\nfinal class Foo { public function run(): void {} }\nfunction helper(): void {}\n", ['helper']],
    'function after an anonymous class with a closure argument' => ["<?php\n\$probe = new class(fn () => 1) { public function run(): void {} };\nfunction helper(): void {}\n", ['helper']],
    'function after a class constant fetch' => ["<?php\n\$name = Foo::class;\nfunction helper(): void {}\n", ['helper']],
    'two functions' => ["<?php\nfunction first(): void {}\nfunction second(): void {}\n", ['first', 'second']],
]);

it('detects every named function a test file declares', function (string $source, array $functions): void {
    $root = SourceSandbox::create(['Feature/FooTest.php' => $source]);

    try {
        expect(new TestHelperFunctionGuard($root)->violations())->toBe(['Feature/FooTest.php' => $functions]);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('functions declared in a test file');

dataset('files the test helper function guard allows', [
    'method in a class' => ["<?php\nfinal class Foo { public static function helper(): void {} }\n"],
    'method in a trait' => ["<?php\ntrait Helps { public function helper(): void {} }\n"],
    'method in an interface' => ["<?php\ninterface Probe { public function helper(): void; }\n"],
    'method in an enum' => ["<?php\nenum Status: string { case A = 'a'; public function label(): string { return 'a'; } }\n"],
    'method of an anonymous class' => ["<?php\n\$probe = new class(fn () => 1) { public function helper(): void {} };\n"],
    'closure' => ["<?php\n\$helper = function (): void {};\n\$other = static function (): void {};\n"],
    'arrow function' => ["<?php\n\$helper = fn (): int => 1;\n"],
    'closure after a class constant fetch' => ["<?php\n\$name = Foo::class;\n\$helper = function (): void {};\n"],
    'imported function' => ["<?php\nuse function Pest\\Laravel\\actingAs;\n"],
    'function in a string' => ["<?php\n\$source = 'function helper() {}';\n"],
    'function in a heredoc' => ["<?php\n\$source = <<<'PHP'\nfunction helper() {}\nPHP;\n"],
    'function in a comment' => ["<?php\n// function helper() {}\n"],
]);

it('allows methods, closures and function-shaped text in test files', function (string $source): void {
    $root = SourceSandbox::create(['Feature/FooTest.php' => $source]);

    try {
        expect(new TestHelperFunctionGuard($root)->violations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('files the test helper function guard allows');

it('keeps every function out of the test files', function (): void {
    expect(new TestHelperFunctionGuard(dirname(__DIR__))->violations())->toBe([]);
});

it('names the files relative to the tests directory when the checkout sits under a dot directory', function (): void {
    $root = SourceSandbox::create([
        'tests/Feature/BarTest.php' => "<?php\nfunction helper(): void {}\n",
        'tests/Support/Ok.php' => "<?php\nfinal class Ok { public static function run(): void {} }\n",
    ], prefix: '.checkout-');

    try {
        expect(new TestHelperFunctionGuard($root . '/tests')->violations())->toBe(['Feature/BarTest.php' => ['helper']]);
    } finally {
        SourceSandbox::remove($root);
    }
});
