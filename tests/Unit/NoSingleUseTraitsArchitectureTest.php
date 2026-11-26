<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\SingleUseTraitGuard;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('traits with exactly one user', [
    'one class uses it' => [["<?php\nnamespace App\\Support;\ntrait Lonely {}\n", "<?php\nnamespace App\\One;\nuse App\\Support\\Lonely;\nfinal class A { use Lonely; }\n"], 'App\\Support\\Lonely'],
    'a class that uses it twice counts once' => [["<?php\nnamespace App;\ntrait T {}\nfinal class A { use T; use T; }\n"], 'App\\T'],
    'a trait that only another trait uses' => [["<?php\nnamespace App;\ntrait Inner {}\ntrait Outer { use Inner; }\n"], 'App\\Inner'],
    'one enum uses it' => [["<?php\nnamespace App;\ntrait HasLabel {}\nenum Status { use HasLabel; case A; }\n"], 'App\\HasLabel'],
    'the same trait written fully qualified' => [["<?php\nnamespace App\\Support;\ntrait Lonely {}\n", "<?php\nnamespace App\\Two;\nfinal class B { use \\App\\Support\\Lonely; }\n"], 'App\\Support\\Lonely'],
]);

it('detects a trait that exactly one class uses', function (array $sources, string $trait): void {
    $violations = new SingleUseTraitGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSources(Narrow::strings($sources));

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith($trait . ': a trait used by exactly one class')
        ->and($violations[0])->toContain('move its members into that class', 'parent or a collaborator');
})->with('traits with exactly one user');

dataset('traits that are shared or unused', [
    'two classes share it' => [["<?php\nnamespace App\\Support;\ntrait Shared {}\n", "<?php\nnamespace App\\One;\nuse App\\Support\\Shared;\nfinal class A { use Shared; }\n", "<?php\nnamespace App\\Two;\nfinal class B { use \\App\\Support\\Shared; }\n"]],
    'a trait nobody uses is dead code for another guard' => [["<?php\nnamespace App;\ntrait Unused {}\n"]],
    'two anonymous classes' => [["<?php\nnamespace App;\ntrait T {}\n\$x = new class() { use T; };\n\$y = new class() { use T; };\n"]],
    'a class and a trait use it' => [["<?php\nnamespace App;\ntrait T {}\ntrait Outer { use T; }\nfinal class A { use T; }\n"]],
]);

it('accepts a shared trait and a trait nobody uses', function (array $sources): void {
    expect(new SingleUseTraitGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->inSources(Narrow::strings($sources)))->toBe([]);
})->with('traits that are shared or unused');

it('counts the distinct classes that use each trait', function (): void {
    $sources = [
        "<?php\nnamespace App\\Support;\ntrait Shared {}\ntrait Lonely {}\ntrait Unused {}\n",
        "<?php\nnamespace App\\One;\nuse App\\Support\\Shared;\nuse App\\Support\\Lonely;\nfinal class A { use Shared; use Lonely; }\n",
        "<?php\nnamespace App\\Two;\nfinal class B { use \\App\\Support\\Shared; }\n",
    ];

    expect(new SingleUseTraitGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->traitUsers($sources))->toBe([
        'App\\Support\\Lonely' => 1,
        'App\\Support\\Shared' => 2,
        'App\\Support\\Unused' => 0,
    ]);
});

it('reads a tree under a dot directory', function (): void {
    $root = SourceSandbox::create([
        'app/Support/Lonely.php' => "<?php\nnamespace App\\Support;\ntrait Lonely {}\n",
        'app/One/A.php' => "<?php\nnamespace App\\One;\nuse App\\Support\\Lonely;\nfinal class A { use Lonely; }\n",
    ], prefix: '.checkout-');

    try {
        $violations = new SingleUseTraitGuard(new PhpFileInspector(), $root, 'app')->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('App\\Support\\Lonely: a trait used by exactly one class');
});

it('lets no trait in app be used by exactly one class', function (): void {
    expect(new SingleUseTraitGuard(new PhpFileInspector(), dirname(__DIR__, 2), 'app')->violations())->toBe([]);
});
