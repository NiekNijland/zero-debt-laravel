<?php

declare(strict_types=1);

use Tests\Support\Architecture\TestCaseFixtureGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('a base test case that carries a fixture', [
    'a typed public property' => ["<?php\nabstract class TestCase\n{\n    public Tenant \$tenant;\n}\n", '$tenant'],
    'a public property with a default' => ["<?php\nabstract class TestCase\n{\n    public array \$rows = [];\n}\n", '$rows'],
    'an untyped public property' => ["<?php\nabstract class TestCase\n{\n    public \$user;\n}\n", '$user'],
]);

it('detects a fixture property on a base test case', function (string $source, string $property): void {
    $violations = new TestCaseFixtureGuard(dirname(__DIR__, 2), ['tests/TestCase.php'], 'phpstan-tests.neon')->fixtureProperties($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($property, 'fixture shared by every test', 'build the state in the test');
})->with('a base test case that carries a fixture');

dataset('a base test case without a fixture', [
    'a public method' => ["<?php\nabstract class TestCase\n{\n    public function tenant(): Tenant {}\n}\n"],
    'a public static method' => ["<?php\nabstract class TestCase\n{\n    public static function boot(): void {}\n}\n"],
    'a public constant' => ["<?php\nabstract class TestCase\n{\n    public const string NAME = 'x';\n}\n"],
    'a private property' => ["<?php\nabstract class TestCase\n{\n    private array \$cache = [];\n}\n"],
]);

it('accepts a base test case with methods, constants and private state', function (string $source): void {
    expect(new TestCaseFixtureGuard(dirname(__DIR__, 2), ['tests/TestCase.php'], 'phpstan-tests.neon')->fixtureProperties($source))->toBe([]);
})->with('a base test case without a fixture');

it('detects a test case listed as a universal object crate in the analyser config', function (): void {
    $guard = new TestCaseFixtureGuard(dirname(__DIR__, 2), [], 'phpstan-tests.neon');
    $listed = $guard->cratesIn("parameters:\n    universalObjectCratesClasses:\n        - Tests\\TestCase\n");

    expect($listed)->toHaveCount(1)
        ->and($listed[0])->toContain('universal object crate', 'remove the line')
        ->and($guard->cratesIn("parameters:\n    universalObjectCratesClasses:\n        - stdClass\n"))->toBe([]);
});

it('fails when a listed file does not exist', function (): void {
    $root = SourceSandbox::create(['phpstan-tests.neon' => "parameters:\n    level: max\n"]);

    try {
        expect(fn (): array => new TestCaseFixtureGuard($root, ['tests/TestCase.php'], 'phpstan-tests.neon')->violations())->toThrow(InvalidArgumentException::class, 'a guard over a missing file would pass without reading it');
    } finally {
        SourceSandbox::remove($root);
    }
});

it('reads the files under a dot directory and names them relative to the root', function (): void {
    $root = SourceSandbox::create([
        'tests/TestCase.php' => "<?php\nabstract class TestCase\n{\n    public Tenant \$tenant;\n}\n",
        'phpstan-tests.neon' => "parameters:\n    level: max\n",
    ], prefix: '.checkout-');

    try {
        $violations = new TestCaseFixtureGuard($root, ['tests/TestCase.php'], 'phpstan-tests.neon')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('tests/TestCase.php: a public property $tenant');
});

it('keeps fixture properties off the test cases and test cases off the crate list', function (): void {
    expect(new TestCaseFixtureGuard(dirname(__DIR__, 2), ['tests/TestCase.php'], 'phpstan-tests.neon')->violations())->toBe([]);
});
