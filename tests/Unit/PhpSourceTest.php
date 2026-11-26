<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use Tests\Support\Architecture\PhpSource;
use Tests\Support\Architecture\TemporarySource;

uses()->group('arch');

it('reads the statements of a file with every name resolved', function (): void {
    $statements = TemporarySource::with("<?php\nnamespace App;\nuse Illuminate\\Support\\Str as S;\nfinal class A extends \\Base implements S {}\n", PhpSource::statements(...));
    $namespace = $statements[0];

    expect($namespace)->toBeInstanceOf(Namespace_::class);

    $class = $namespace instanceof Namespace_ ? $namespace->stmts[1] : null;

    expect($class)->toBeInstanceOf(Class_::class)
        ->and($class instanceof Class_ ? $class->namespacedName?->toString() : null)->toBe('App\A')
        ->and($class instanceof Class_ ? $class->extends?->toString() : null)->toBe('Base')
        ->and($class instanceof Class_ ? $class->implements[0]->toString() : null)->toBe(Str::class);
});

it('reads an empty file as no statements', function (): void {
    expect(TemporarySource::with('', PhpSource::statements(...)))->toBe([]);
});

it('fails on a file that does not exist, so a renamed file is not read as an empty tree', function (): void {
    expect(fn (): array => PhpSource::statements(dirname(__DIR__, 2) . '/tests/Support/Architecture/Renamed.php'))->toThrow(RuntimeException::class, 'does not exist');
});
