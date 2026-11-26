<?php

declare(strict_types=1);

use Tests\Support\Architecture\EnumCaseGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('enum cases that are not UPPER_CASE', [
    'camel case' => ["<?php\nenum Status: string { case Placed = 'p'; }\n", 'the enum case Placed is not UPPER_CASE: write it as PLACED'],
    'a mixed case in a second case' => ["<?php\nenum Status: string { case PLACED = 'p'; case shippedLate = 's'; }\n", 'shippedLate is not UPPER_CASE: write it as SHIPPED_LATE'],
]);

it('detects an enum case that is not UPPER_CASE', function (string $source, string $words): void {
    $violations = new EnumCaseGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain($words);
})->with('enum cases that are not UPPER_CASE');

it('accepts UPPER_CASE cases and a constant in a class that is not an enum', function (): void {
    $guard = new EnumCaseGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app');

    expect($guard->inSource("<?php\nenum Status: string { case PLACED = 'p'; case SHIPPED_LATE = 's'; const Default = self::PLACED; }\nfinal class A { public const Mixed = 1; }\n"))->toBe([]);
});

it('reads an enum below a checkout under a dot directory and fails on a folder without one', function (): void {
    $root = SourceSandbox::create(['app/Status.php' => "<?php\nenum Status: string { case Placed = 'p'; }\n", 'app/Plain.php' => "<?php\nfinal class Plain {}\n", 'none/Plain.php' => "<?php\nfinal class Plain {}\n"], prefix: '.checkout-');

    try {
        $violations = new EnumCaseGuard(new PhpFileInspector(), $root, 'app')->violations();
        $none = new EnumCaseGuard(new PhpFileInspector(), $root, 'none')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Status.php: line 2: the enum case Placed')
        ->and($none)->toHaveCount(1)
        ->and($none[0])->toContain('no enum was read');
});

it('writes every enum case of app in UPPER_CASE', function (): void {
    expect(new EnumCaseGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
