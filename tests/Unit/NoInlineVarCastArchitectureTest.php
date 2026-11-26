<?php

declare(strict_types=1);

use Tests\Support\Architecture\InlineVarCastGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('inline var casts', [
    'assignment in a method' => ["<?php\nfinal class C { public function f(): void { /** @var int \$x */ \$x = g(); } }\n"],
    'assignment in a function' => ["<?php\nfunction f(): void {\n    /** @var list<int> \$ids */\n    \$ids = g();\n}\n"],
    'return in a method' => ["<?php\nfinal class C { public function f(): array { /** @var list<string> */ return g(); } }\n"],
    'foreach in a closure' => ["<?php\n\$f = function (): void { /** @var Foo \$item */ foreach (g() as \$item) {} };\n"],
    'statement in an arrow function body' => ["<?php\nfinal class C { public function f(): void { \$g = fn () => h(/** @var int \$x */ \$x = 1); } }\n"],
    'phpstan-var' => ["<?php\nfinal class C { public function f(): void { /** @phpstan-var int \$x */ \$x = g(); } }\n"],
    'multi-line docblock' => ["<?php\nfinal class C { public function f(): void {\n    /**\n     * @var int \$x\n     */\n    \$x = g();\n} }\n"],
    'top-level script statement' => ["<?php\n/** @var array<string, int> \$map */\n\$map = g();\n"],
]);

it('detects a @var docblock on a statement or an expression inside a body', function (string $source): void {
    $violations = new InlineVarCastGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('inline @var', 'typed helper', 'assertion the code runs', 'keep @var for a property or a constant');
})->with('inline var casts');

dataset('var docblocks on a declaration', [
    'a property' => ["<?php\nfinal class C {\n    /** @var list<int> */\n    private array \$ids = [];\n}\n"],
    'a class constant' => ["<?php\nfinal class C {\n    /** @var array<string, int> */\n    private const array MAP = [];\n}\n"],
    'a promoted parameter' => ["<?php\nfinal class C {\n    public function __construct(\n        /** @var list<string> */\n        public array \$names = [],\n    ) {\n    }\n}\n"],
    'a property of an anonymous class in a body' => ["<?php\nfinal class C { public function f(): object { return new class() {\n    /** @var list<int> */\n    public array \$values = [];\n}; } }\n"],
    'a top-level constant' => ["<?php\nnamespace App;\n/** @var list<int> */\nconst TOP = [];\n"],
    'a key of a returned config array' => ["<?php\nreturn [\n    /** @var list<string> */\n    'config' => [],\n];\n"],
    'a docblock without @var in a body' => ["<?php\nfinal class C { public function f(): void { \x2f** explains the call *\x2f g(); } }\n"],
]);

it('accepts @var on properties, constants and promoted parameters and the near misses', function (string $source): void {
    expect(new InlineVarCastGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app'])->inSource($source))->toBe([]);
})->with('var docblocks on a declaration');

it('reads a tree under a dot directory, skips a folder that is absent and names the file relative to the root', function (): void {
    $root = SourceSandbox::create([
        'app/Casts.php' => "<?php\nfinal class C { public function f(): void {\n    /** @var int \$x */\n    \$x = g();\n} }\n",
        'config/app.php' => "<?php\nreturn [];\n",
    ], prefix: '.checkout-');

    try {
        $violations = new InlineVarCastGuard(new PhpFileInspector(), $root, ['app', 'config', 'routes'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('app/Casts.php:3: an inline @var narrows a type');
});

it('lets no file in app, config, database or routes narrow a type with an inline @var', function (): void {
    expect(new InlineVarCastGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['app', 'config', 'database', 'routes'])->violations())->toBe([]);
});
