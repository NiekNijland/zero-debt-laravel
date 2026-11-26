<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TestClassGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$suites = ['tests/Unit', 'tests/Feature'];

dataset('classes declared in a test file', [
    'helper class' => ["<?php\nfinal class Clock {}\nit('works', fn () => expect(true)->toBeTrue());\n", ['Clock']],
    'readonly helper class' => ["<?php\nfinal readonly class Handler {}\n", ['Handler']],
    'class behind an attribute' => ["<?php\n#[Attribute] final class Marker {}\n", ['Marker']],
    'upper case keyword' => ["<?php\nFINAL CLASS Shouty {}\n", ['Shouty']],
    'interface' => ["<?php\ninterface Probe {}\n", ['Probe']],
    'trait' => ["<?php\ntrait Helps {}\n", ['Helps']],
    'enum' => ["<?php\nenum Status: string { case A = 'a'; }\n", ['Status']],
    'second class next to the test case' => ["<?php\nnamespace Tests\\Unit;\nfinal class FooTest extends \\PHPUnit\\Framework\\TestCase {}\nfinal class Extra {}\n", ['Tests\\Unit\\Extra']],
]);

it('detects every class a test file declares besides its own test case', function (string $source, array $types) use ($suites): void {
    $root = SourceSandbox::create(['tests/Feature/FooTest.php' => $source, 'tests/Unit/PlainTest.php' => "<?php\n"]);

    try {
        expect(new TestClassGuard(new PhpFileInspector(), $root, $suites)->violations())->toBe(['tests/Feature/FooTest.php' => $types]);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('classes declared in a test file');

dataset('files the test class guard allows', [
    'anonymous class' => ['tests/Unit/FooTest.php', "<?php\n\$clock = new class() { public int \$now = 0; };\n"],
    'anonymous class after new' => ['tests/Unit/FooTest.php', "<?php\nfunction clock(): object\n{\n    return new class extends ArrayObject {};\n}\n"],
    'test case named after the file' => ['tests/Feature/FooTest.php', "<?php\nnamespace Tests\\Feature;\nfinal class FooTest extends \\PHPUnit\\Framework\\TestCase {}\n"],
    'class in a source string' => ['tests/Unit/FooTest.php', "<?php\n\$source = \"<?php\\nfinal class Inline {}\\n\";\n"],
    'support class' => ['tests/Support/Clock.php', "<?php\nnamespace Tests\\Support;\nfinal class Clock {}\n"],
    'concern' => ['tests/Concerns/Helps.php', "<?php\nnamespace Tests\\Concerns;\ntrait Helps {}\n"],
    'fixture' => ['tests/Fixtures/Events/TestEvent.php', "<?php\nnamespace Tests\\Fixtures\\Events;\nclass TestEvent {}\n"],
    'fixture next to a test' => ['tests/Feature/Checkout/fixtures/FakeJob.php', "<?php\nnamespace Tests\\Feature\\Checkout\\fixtures;\nclass FakeJob {}\n"],
]);

it('allows anonymous classes, test cases named after their file and classes outside test files', function (string $path, string $source) use ($suites): void {
    $root = SourceSandbox::create([$path => $source, 'tests/Unit/PlainTest.php' => "<?php\n", 'tests/Feature/PlainTest.php' => "<?php\n"]);

    try {
        expect(new TestClassGuard(new PhpFileInspector(), $root, $suites)->violations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('files the test class guard allows');

it('keeps every class out of the test files', function () use ($suites): void {
    expect(new TestClassGuard(new PhpFileInspector(), dirname(__DIR__, 2), $suites)->violations())->toBe([]);
});

it('names the file relative to the root when the checkout sits under a dot directory', function () use ($suites): void {
    $root = SourceSandbox::create([
        'tests/Unit/FooTest.php' => "<?php\nit('works', fn () => expect(1)->toBe(1));\n",
        'tests/Feature/BarTest.php' => "<?php\nfinal class Clock {}\n",
    ], prefix: '.checkout-');

    try {
        expect(new TestClassGuard(new PhpFileInspector(), $root, $suites)->violations())->toBe(['tests/Feature/BarTest.php' => ['Clock']]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('fails when a suite folder is missing', function (): void {
    $root = SourceSandbox::create(['tests/Unit/FooTest.php' => "<?php\n"]);

    try {
        expect(fn (): array => new TestClassGuard(new PhpFileInspector(), $root, ['tests/Unit', 'tests/Feature'])->violations())->toThrow(InvalidArgumentException::class, 'does not exist');
    } finally {
        SourceSandbox::remove($root);
    }
});
