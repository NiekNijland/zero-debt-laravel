<?php

declare(strict_types=1);

use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\TestTierGuard;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$base = 'Tests\\' . 'TestCase';
$plain = 'PHPUnit\\Framework\\' . 'TestCase';

$tier = static fn (string $root): TestTierGuard => new TestTierGuard(
    new PhpFileInspector(),
    $root,
    'tests',
    'tests/Unit',
    'tests/Pest.php',
    ['app/Support', 'app/Modules/*/Contracts', 'app/Modules/*/Data', 'app/Modules/*/Enums', 'app/Modules/*/Exceptions', 'app/Modules/*/ValueObjects'],
    ['app/Modules/*/Data'],
);

dataset('booting unit test sources', [
    'plain import' => ["<?php\nuse " . $base . ";\nuses(TestCase::class);\n"],
    'aliased import' => ["<?php\nuse " . $base . " as Base;\nuses(Base::class);\n"],
    'group import' => ["<?php\nuse Illuminate\\Foundation\\Testing\\{RefreshDatabase, WithFaker};\nuses(RefreshDatabase::class);\n"],
    'inline fully qualified name' => ["<?php\nuses(\\" . $base . "::class);\n"],
    'class extending a booting base' => ["<?php\nnamespace Tests\\Unit;\nfinal class FooTest extends \\" . $base . " {}\n"],
    'pest extend call' => ["<?php\npest()->extend(\\" . $base . "::class);\n"],
]);

it('detects a booting base in every way a unit test can name it', function (string $source) use ($tier): void {
    $root = SourceSandbox::create(['tests/Unit/FooTest.php' => $source, 'tests/Pest.php' => "<?php\n"]);

    try {
        expect(array_keys($tier($root)->unitTestViolations()))->toBe(['tests/Unit/FooTest.php']);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('booting unit test sources');

it('detects a booting base reached through a support trait', function () use ($tier): void {
    $root = SourceSandbox::create([
        'tests/Concerns/Indirect.php' => "<?php\nnamespace Tests\\Concerns;\nuse Illuminate\\Foundation\\Testing\\LazilyRefreshDatabase;\ntrait Indirect { use LazilyRefreshDatabase; }\n",
        'tests/Support/Deeper.php' => "<?php\nnamespace Tests\\Support;\nuse Tests\\Concerns\\Indirect;\ntrait Deeper { use Indirect; }\n",
        'tests/Unit/FooTest.php' => "<?php\nuses(Tests\\Support\\Deeper::class);\n",
        'tests/Pest.php' => "<?php\n",
    ]);

    try {
        expect($tier($root)->unitTestViolations())->toHaveKey('tests/Unit/FooTest.php');
    } finally {
        SourceSandbox::remove($root);
    }
});

it('detects a booting base bound to the unit directory in Pest.php', function () use ($base, $tier): void {
    $root = SourceSandbox::create([
        'tests/Pest.php' => "<?php\npest()->extend(" . $base . "::class)->in('Feature', 'Unit');\n",
        'tests/Unit/FooTest.php' => "<?php\nit('works', fn () => expect(true)->toBeTrue());\n",
    ]);

    try {
        expect($tier($root)->unitTestViolations())->toHaveKey('tests/Pest.php');
    } finally {
        SourceSandbox::remove($root);
    }
});

dataset('booting Pest bindings', [
    'function call root' => ["<?php\nuses(" . $base . "::class)->in('Unit');\n", 'tests/Pest.php'],
    'no directory in the global file' => ["<?php\npest()->extend(" . $base . "::class);\n", 'tests/Pest.php'],
    'directory constant' => ["<?php\npest()->extend(" . $base . "::class)->in(__DIR__ . '/Unit');\n", 'tests/Pest.php'],
    'relative directory' => ["<?php\npest()->extend(" . $base . "::class)->in('./Unit');\n", 'tests/Pest.php'],
    'directory list' => ["<?php\npest()->extend(" . $base . "::class)->in(['Feature', 'Unit']);\n", 'tests/Pest.php'],
    'class name as string' => ["<?php\npest()->extend('Tests\\\\TestCase')->in('Unit');\n", 'tests/Pest.php'],
    'binding in another file' => ["<?php\npest()->extend(" . $base . "::class)->in('../Unit');\n", 'tests/Feature/BindingTest.php'],
]);

it('detects a booting base bound to the unit directory in every spelling', function (string $source, string $path) use ($tier): void {
    $root = SourceSandbox::create([$path => $source, 'tests/Unit/FooTest.php' => "<?php\nit('works', fn () => expect(true)->toBeTrue());\n"]);

    try {
        expect($tier($root)->unitTestViolations())->toHaveKey($path);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('booting Pest bindings');

it('does not flag a booting base bound to another directory', function () use ($base, $tier): void {
    $root = SourceSandbox::create([
        'tests/Pest.php' => "<?php\npest()->extend(" . $base . "::class)->in('Feature', 'Integration');\n",
        'tests/Unit/FooTest.php' => "<?php\nit('works', fn () => expect(true)->toBeTrue());\n",
    ]);

    try {
        expect($tier($root)->unitTestViolations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('detects a booting trait that lives outside the support directories', function () use ($tier): void {
    $root = SourceSandbox::create([
        'tests/Feature/Helpers/Booted.php' => "<?php\nnamespace Tests\\Feature\\Helpers;\nuse Illuminate\\Foundation\\Testing\\RefreshDatabase;\ntrait Booted { use RefreshDatabase; }\n",
        'tests/Unit/FooTest.php' => "<?php\nuses(Tests\\Feature\\Helpers\\Booted::class);\n",
        'tests/Pest.php' => "<?php\n",
    ]);

    try {
        expect($tier($root)->unitTestViolations())->toHaveKey('tests/Unit/FooTest.php');
    } finally {
        SourceSandbox::remove($root);
    }
});

it('accepts a plain PHPUnit case and a support trait that boots nothing in unit tests', function () use ($plain, $tier): void {
    $root = SourceSandbox::create([
        'tests/Support/Quiet.php' => "<?php\nnamespace Tests\\Support;\nuse Illuminate\\Foundation\\Application;\ntrait Quiet {}\n",
        'tests/Pest.php' => "<?php\npest()->extend(" . $plain . "::class)->in('Unit');\n",
        'tests/Unit/FooTest.php' => "<?php\nuses(Tests\\Support\\Quiet::class);\n",
    ]);

    try {
        expect($tier($root)->unitTestViolations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('keeps every unit test off a booting base class', function () use ($tier): void {
    expect($tier(dirname(__DIR__, 2))->unitTestViolations())->toBe([]);
});

dataset('database layer sources', [
    'model import' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nuse App\\Models\\Tenant\\User;\nfinal class A { public User \$user; }\n"],
    'module model import' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nuse App\\Modules\\Orders\\Models\\Thing;\nfinal class A { public ?Thing \$thing = null; }\n"],
    'aliased import' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nuse App\\Models\\Tenant\\User as Account;\nfinal class A { public function f(Account \$a): void {} }\n"],
    'group import' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nuse App\\Models\\Tenant\\{Order, User};\nfinal class A {}\n"],
    'inline fully qualified name' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nfinal class A { public function f(): mixed { return \\App\\Models\\Tenant\\User::query(); } }\n"],
    'database facade' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nuse Illuminate\\Support\\Facades\\DB;\nfinal class A { public function f(): void { DB::select('select 1'); } }\n"],
    'eloquent builder' => ["<?php\nnamespace App\\Modules\\Orders\\Exceptions;\nuse Illuminate\\Database\\Eloquent\\Builder;\nfinal class A { public function f(Builder \$q): void {} }\n"],
]);

it('detects database layer references in every way a pure layer can name them', function (string $source) use ($tier): void {
    $root = SourceSandbox::create(['app/Modules/Orders/Exceptions/A.php' => $source]);

    try {
        expect(array_keys($tier($root)->pureLayerViolations()))->toBe(['app/Modules/Orders/Exceptions/A.php']);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('database layer sources');

dataset('Data sources that may name a model', [
    'model in a static from factory' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { public function __construct(public string \$id) {} public static function fromModel(User \$user): self { return new self(\$user->name); } }\n"],
    'inline name in a static from factory' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nfinal class A { public static function fromId(int \$id): self { return \\App\\Models\\Tenant\\User::query()->findOrFail(\$id); } }\n"],
    'a private static from factory' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { private static function fromTarget(User \$user): self { return new self(); } }\n"],
]);

it('lets a Data class name a model inside its static from factories', function (string $source) use ($tier): void {
    $root = SourceSandbox::create(['app/Modules/Orders/Data/A.php' => $source]);

    try {
        expect($tier($root)->pureLayerViolations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('Data sources that may name a model');

dataset('Data sources that may not name a model', [
    'a property type' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { public function __construct(public User \$user) {} public static function fromModel(User \$user): self { return new self(\$user); } }\n"],
    'a constructor default' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { public function __construct(public string \$id = User::class) {} }\n"],
    'a static method that is not a from factory' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { public static function forUser(User \$user): self { return new self(); } }\n"],
    'an instance method named from' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { public function fromModel(User \$user): self { return \$this; } }\n"],
    'a private helper that is not a from factory' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse App\\Models\\Tenant\\User;\nfinal class A { public static function fromModel(User \$user): self { return new self(self::label(\$user)); } private static function label(User \$user): string { return \$user->name; } }\n"],
    'the database facade in a from factory' => ["<?php\nnamespace App\\Modules\\Orders\\Data;\nuse Illuminate\\Support\\Facades\\DB;\nfinal class A { public static function fromId(int \$id): self { DB::table('users')->find(\$id); return new self(); } }\n"],
]);

it('keeps every other part of a Data class off the models', function (string $source) use ($tier): void {
    $root = SourceSandbox::create(['app/Modules/Orders/Data/A.php' => $source]);

    try {
        expect(array_keys($tier($root)->pureLayerViolations()))->toBe(['app/Modules/Orders/Data/A.php']);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('Data sources that may not name a model');

it('ignores pure layer files that never reach the database layer', function () use ($tier): void {
    $root = SourceSandbox::create(['app/Modules/Orders/Enums/A.php' => "<?php\nnamespace App\\Modules\\Orders\\Enums;\nuse Illuminate\\Support\\Collection;\nenum A: string { case X = 'x'; }\n"]);

    try {
        expect($tier($root)->pureLayerViolations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('fails when none of the pure layers exists', function () use ($tier): void {
    $root = SourceSandbox::create(['README.txt' => 'x']);

    try {
        expect(fn (): array => $tier($root)->pureLayerViolations())->toThrow(InvalidArgumentException::class, 'a guard that reads no file passes without checking');
    } finally {
        SourceSandbox::remove($root);
    }
});

it('keeps every pure layer off the database layer', function () use ($tier): void {
    $root = dirname(__DIR__, 2);

    expect($tier($root)->pureLayerViolations())->toBe([])
        ->and(file_exists($root . '/tests/Support/Architecture/pure-layer-allowlist.php'))->toBeFalse();
});

it('names the files relative to the root when the checkout sits under a dot directory', function () use ($base, $tier): void {
    $root = SourceSandbox::create([
        'tests/Unit/FooTest.php' => "<?php\nuse " . $base . ";\nuses(TestCase::class);\n",
        'tests/Feature/BarTest.php' => "<?php\nuses(" . $base . "::class);\n",
        'app/Support/A.php' => "<?php\nnamespace App\\Support;\nuse Illuminate\\Support\\Facades\\DB;\nfinal class A {}\n",
    ], prefix: '.checkout-');

    try {
        expect(array_keys($tier($root)->unitTestViolations()))->toBe(['tests/Unit/FooTest.php'])
            ->and(array_keys($tier($root)->pureLayerViolations()))->toBe(['app/Support/A.php']);
    } finally {
        SourceSandbox::remove($root);
    }
});
