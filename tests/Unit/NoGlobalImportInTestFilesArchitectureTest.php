<?php

declare(strict_types=1);

use Tests\Support\Architecture\GlobalImportGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('a global class imported in a file without a namespace', [
    'a bare import' => ["<?php\n\nuse RuntimeException;\n\nit('throws', fn () => throw new RuntimeException());\n"],
    'two bare imports in one statement' => ["<?php\nuse Closure, Countable;\n"],
    'a bare import with a leading backslash' => ["<?php\nuse \\Closure;\n"],
    'an import after the strict types line' => ["<?php\ndeclare(strict_types=1);\nuse Closure;\n"],
    'a comment after the line' => ["<?php\n\nuse Exception; /" . "/ for the test\n"],
    'an indented import' => ["<?php\n\n    use Exception;\n"],
    'a file with Windows line breaks' => ["<?php\r\n\r\nuse Exception;\r\n\r\nit('works', fn () => 1);\r\n"],
    'a digit in the name' => ["<?php\nuse SQLite3;\n"],
    'two names in one import' => ["<?php\nuse Exception, Closure;\n"],
    'a leading backslash' => ["<?php\nuse \\Exception;\n"],
    'a function import of a bare name' => ["<?php\nuse function strlen;\n"],
    'a constant import of a bare name' => ["<?php\nuse const PHP_EOL;\n"],
    'a global namespace block' => ["<?php\nnamespace {\n    use Exception;\n}\n"],
    'the word namespace in a heredoc' => ["<?php\n\$sql = <<<TXT\nnamespace App;\nTXT;\nuse Exception;\n"],
    'the word namespace in a comment' => ["<?php\n/*\nnamespace App;\n*/\nuse Exception;\n"],
]);

it('detects the import of a global class in a file without a namespace', function (string $source): void {
    $violations = new GlobalImportGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['tests'])->inSource($source);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('bare name in a file without a namespace', 'delete the use line', 'declare a namespace');
})->with('a global class imported in a file without a namespace');

dataset('imports that are not the mistake', [
    'a namespaced import in a file with a namespace' => ["<?php\nnamespace Tests\\Support;\nuse RuntimeException;\n"],
    'a qualified import in a file without a namespace' => ["<?php\nuse Tests\\Support\\SourceSandbox;\n"],
    'an aliased import' => ["<?php\nuse RuntimeException as Failure;\n"],
    'a bare import inside a namespace block' => ["<?php\nnamespace Tests;\nuse Closure;\n"],
    'a grouped import' => ["<?php\nuse Tests\\Support\\{SourceSandbox, Globs};\n"],
    'a bare import named in a string' => ["<?php\n\$source = 'use Closure;';\n"],
    'no import' => ["<?php\nit('works', fn () => expect(true)->toBeTrue());\n"],
    'a qualified function import' => ["<?php\nuse function Tests\\Support\\helper;\n"],
    'the word namespace at the start of a line in a heredoc, with a namespace declared' => ["<?php\nnamespace Tests\\Support;\nuse Exception;\n\$text = <<<TXT\nuse Closure;\nTXT;\n"],
    'a bare import inside a text, a comment or a string' => ["<?php\n// use Exception;\n\$text = 'use Exception;';\n"],
    'an import under a named namespace block' => ["<?php\nnamespace Tests\\Support {\n    use Exception;\n}\n"],
    'a group import of qualified names' => ["<?php\nuse Tests\\Support\\{SourceSandbox, Globs};\n"],
]);

it('accepts the imports that PHP honors and files without an import', function (string $source): void {
    expect(new GlobalImportGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['tests'])->inSource($source))->toBe([]);
})->with('imports that are not the mistake');

it('reads a tree under a dot directory and names the file relative to the root', function (): void {
    $root = SourceSandbox::create([
        'tests/Unit/BadTest.php' => "<?php\nuse Closure;\n",
        'tests/Unit/GoodTest.php' => "<?php\nuse Tests\\Support\\SourceSandbox;\n",
    ], prefix: '.checkout-');

    try {
        $violations = new GlobalImportGuard(new PhpFileInspector(), $root, ['tests'])->violations();
    } finally {
        SourceSandbox::remove($root);
    }

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toStartWith('tests/Unit/BadTest.php: imports a global class by its bare name');
});

it('fails when a folder is missing', function (): void {
    $root = SourceSandbox::create(['README.txt' => 'x']);

    try {
        expect(fn (): array => new GlobalImportGuard(new PhpFileInspector(), $root, ['tests'])->violations())->toThrow(InvalidArgumentException::class, 'does not exist');
    } finally {
        SourceSandbox::remove($root);
    }
});

it('lets no test file import a global class by its bare name', function (): void {
    expect(new GlobalImportGuard(new PhpFileInspector(), dirname(__DIR__, 2), ['tests'])->violations())->toBe([]);
});
