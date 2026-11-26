<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use Tests\Support\Architecture\GuardShapeGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

final class GuardShapeArchitectureHelpers
{
    public const string FAILING = "it('detects', function (string \$source): void {\n    expect((new FooGuard())->inSource(\$source))->toContain('x');\n})->with([['a']]);\n";

    public const string PASSING = "it('accepts', function (): void {\n    expect((new FooGuard())->inSource('a'))->toBe([]);\n});\n";

    public const string REAL_TREE = "it('keeps the tree clean', function (): void {\n    expect((new FooGuard(dirname(__DIR__, 2)))->violations())->toBe([]);\n});\n";

    public const string IMPORT = "<?php\nuse Tests\\Support\\Architecture\\FooGuard;\nuses()->group('arch');\n";

    public static function detector(string $body = 'return 1;', string $name = 'FooGuard'): string
    {
        return "<?php\nnamespace Tests\\Support\\Architecture;\nfinal class " . $name . ' { public function f(): mixed { ' . $body . " } }\n";
    }

    public static function test(string $parts = self::FAILING . self::PASSING . self::REAL_TREE, string $import = self::IMPORT): string
    {
        return $import . $parts;
    }

    /**
     * @param array<string, string> $files
     * @return array<string, string>
     */
    public static function tree(array $files = []): array
    {
        return [
            'tests/Support/Architecture/FooGuard.php' => self::detector(),
            'tests/Unit/FooArchitectureTest.php' => self::test(),
            'tests/Feature/Placeholder.txt' => 'x',
            'app/Models/Note.php' => '<?php',
            'composer.json' => '{}',
            ...$files,
        ];
    }

    /**
     * @param array<string, string> $files
     * @param non-empty-string $prefix
     * @return list<string>
     */
    public static function violations(array $files, string $prefix = 'source-sandbox-'): array
    {
        $root = SourceSandbox::create($files, $prefix);

        try {
            return new GuardShapeGuard(new PhpFileInspector(), $root, 'tests/Support/Architecture', ['tests/Unit', 'tests/Feature'], ['arch'], ['tests/Unit'])->violations();
        } finally {
            SourceSandbox::remove($root);
        }
    }
}
