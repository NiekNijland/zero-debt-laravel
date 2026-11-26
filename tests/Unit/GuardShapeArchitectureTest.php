<?php

declare(strict_types=1);

use Tests\Support\Architecture\GuardShapeGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Narrow;
use Tests\Support\Unit\GuardShapeArchitectureHelpers;

uses()->group('arch');

dataset('guards that break the shape', [
    'a detector with the path of a folder' => [['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector("return 'app/Models';")], 'FooGuard.php: the literal "app/Models" names a path of the repository'],
    'a detector with a folder under a leading slash' => [['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector("return '/app';")], 'the literal "/app" names a path of the repository'],
    'a detector with the name of a file' => [['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector("return 'composer.json';")], 'the literal "composer.json" names a path of the repository'],
    'a detector with a path in a concatenation' => [['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector("return \$this->root . '/app/Models/Note.php';")], 'names a path of the repository'],
    'a detector with a path in an interpolated string' => [['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector('return "{$this->root}/composer.json";')], 'the literal "/composer.json" names a path of the repository'],
    'a detector that no test names' => [['tests/Unit/FooArchitectureTest.php' => "<?php\narch('x')->expect('App')->toBeClasses();\n"], 'FooGuard is named by no guard test'],
    'a detector whose name ends in Helpers' => [['tests/Support/Architecture/FooHelpers.php' => GuardShapeArchitectureHelpers::detector(name: 'FooHelpers')], 'FooHelpers ends in Helpers or Scenario'],
    'a test without failing examples' => [['tests/Unit/FooArchitectureTest.php' => GuardShapeArchitectureHelpers::test(GuardShapeArchitectureHelpers::PASSING . GuardShapeArchitectureHelpers::REAL_TREE)], 'the guard test has no failing examples'],
    'a test without passing examples' => [['tests/Unit/FooArchitectureTest.php' => GuardShapeArchitectureHelpers::test(GuardShapeArchitectureHelpers::FAILING . GuardShapeArchitectureHelpers::REAL_TREE)], 'the guard test has no passing examples'],
    'a test without a real-tree case' => [['tests/Unit/FooArchitectureTest.php' => GuardShapeArchitectureHelpers::test(GuardShapeArchitectureHelpers::FAILING . GuardShapeArchitectureHelpers::PASSING)], 'the guard test has no real-tree case'],
    'a real-tree case that is a dataset' => [['tests/Unit/FooArchitectureTest.php' => GuardShapeArchitectureHelpers::test(GuardShapeArchitectureHelpers::FAILING . GuardShapeArchitectureHelpers::PASSING . str_replace('});', "})->with([['a']]);", GuardShapeArchitectureHelpers::REAL_TREE))], 'the guard test has no real-tree case'],
    'a test outside the group' => [['tests/Unit/FooArchitectureTest.php' => GuardShapeArchitectureHelpers::test(import: "<?php\nuse Tests\\Support\\Architecture\\FooGuard;\n")], 'the guard test is not in the group arch'],
    'a test in another group' => [['tests/Unit/FooArchitectureTest.php' => GuardShapeArchitectureHelpers::test(import: "<?php\nuse Tests\\Support\\Architecture\\FooGuard;\nuses()->group('slow');\n")], 'the guard test is not in the group arch'],
]);

it('fails the guard that breaks the shape, with the three parts and the directory scan in the message', function (array $files, string $words): void {
    $violations = GuardShapeArchitectureHelpers::violations(GuardShapeArchitectureHelpers::tree(Narrow::stringMap($files)));

    expect($violations)->not->toBe([])
        ->and(implode("\n", $violations))->toContain($words);
})->with('guards that break the shape');

it('names the directory scan in the message of a literal path and the three parts in the message of a missing test', function (): void {
    $path = GuardShapeArchitectureHelpers::violations(GuardShapeArchitectureHelpers::tree(['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector("return 'app/Models';")]));
    $untested = GuardShapeArchitectureHelpers::violations(GuardShapeArchitectureHelpers::tree(['tests/Unit/FooArchitectureTest.php' => '<?php']));

    expect($path[0])->toContain('reflection or a directory scan')
        ->and($untested[0])->toContain('failing examples')
        ->and($untested[0])->toContain('passing examples')
        ->and($untested[0])->toContain('one case over the real tree');
});

dataset('guards that keep the shape', [
    'a whole guard with its test' => [[]],
    'a bare word that is also a folder' => [['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector("return ['app', 'config'];")]],
    'a pattern that holds a folder name' => [['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector("return '/^app\\\\//';")]],
    'a path that no file has' => [['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector("return 'app/Missing/Thing.php';")]],
    'a sentence that names a folder' => [['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector("return 'put it in app/Models please';")]],
    'a glob' => [['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector("return 'app/*.php';")]],
    'a class name' => [['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector("return 'App\\\\Models\\\\Note';")]],
    'a reader class that is no guard and no test names' => [['tests/Support/Architecture/FooReader.php' => GuardShapeArchitectureHelpers::detector(name: 'FooReader')]],
    'a test that names no guard' => [['tests/Unit/OtherTest.php' => "<?php\narch('x')->expect('App')->toBeClasses();\n"]],
    'a guard imported under an alias' => [['tests/Unit/FooArchitectureTest.php' => GuardShapeArchitectureHelpers::test(import: "<?php\nuse Tests\\Support\\Architecture\\FooGuard as Foo;\nuses()->group('arch');\n")]],
    'a group on the test instead of the file' => [['tests/Unit/FooArchitectureTest.php' => GuardShapeArchitectureHelpers::test(str_replace(['});', "[['a']]);"], ["})->group('arch');", "[['a']])->group('arch');"], GuardShapeArchitectureHelpers::FAILING . GuardShapeArchitectureHelpers::PASSING . GuardShapeArchitectureHelpers::REAL_TREE), "<?php\nuse Tests\\Support\\Architecture\\FooGuard;\n")]],
    'a feature test that is not in the group' => [['tests/Unit/FooArchitectureTest.php' => '<?php', 'tests/Feature/FooArchitectureTest.php' => GuardShapeArchitectureHelpers::test(import: "<?php\nuse Tests\\Support\\Architecture\\FooGuard;\n")]],
    'a real-tree case that asserts with toBeEmpty' => [['tests/Unit/FooArchitectureTest.php' => GuardShapeArchitectureHelpers::test(GuardShapeArchitectureHelpers::FAILING . GuardShapeArchitectureHelpers::PASSING . str_replace('->toBe([])', '->toBeEmpty()', GuardShapeArchitectureHelpers::REAL_TREE))]],
]);

it('accepts a detector with its test and the near misses of a path', function (array $files): void {
    expect(GuardShapeArchitectureHelpers::violations(GuardShapeArchitectureHelpers::tree(Narrow::stringMap($files))))->toBe([]);
})->with('guards that keep the shape');

it('reads a checkout under a dot directory and names the files relative to the root', function (): void {
    $violations = GuardShapeArchitectureHelpers::violations(GuardShapeArchitectureHelpers::tree(['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector("return 'app/Models';")]), '.checkout-');

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('tests/Support/Architecture/FooGuard.php: the literal "app/Models"');
});

it('fails on a guard folder or a test folder that does not exist', function (): void {
    $files = ['tests/Support/Architecture/FooGuard.php' => GuardShapeArchitectureHelpers::detector(), 'app/Models/Note.php' => '<?php'];

    expect(static fn (): array => GuardShapeArchitectureHelpers::violations($files))->toThrow(InvalidArgumentException::class, 'does not exist');
});

it('holds every guard of the guard folder to the shape: no path in a detector, and a thin test with the three parts', function (): void {
    $guard = new GuardShapeGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'tests/Support/Architecture', ['tests/Unit', 'tests/Feature'], ['arch'], ['tests/Unit']);

    expect($guard->violations())->toBe([]);
});
