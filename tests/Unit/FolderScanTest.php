<?php

declare(strict_types=1);

use Tests\Support\Architecture\FolderScan;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\SourceSandbox;

uses()->group('arch');

it('hands every php file of the folders to the check with its path relative to the root', function (): void {
    $root = SourceSandbox::create(['one/a.php' => '<?php a', 'two/deep/b.php' => '<?php b', 'two/readme.md' => 'x', 'three/c.php' => '<?php c'], prefix: '.checkout-');

    try {
        $findings = FolderScan::over(new PhpFileInspector(), $root, ['one', 'two'], static fn (string $path, string $source): array => [$source . ' in ' . $path]);

        expect($findings)->toBe(['one/a.php: <?php a in one/a.php', 'two/deep/b.php: <?php b in two/deep/b.php']);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('reports no finding when the check answers none, and fails on a folder that does not exist', function (): void {
    $root = SourceSandbox::create(['one/a.php' => '<?php']);

    try {
        expect(FolderScan::over(new PhpFileInspector(), $root, ['one'], static fn (): array => []))->toBe([])
            ->and(static fn (): array => FolderScan::over(new PhpFileInspector(), $root, ['missing'], static fn (): array => []))->toThrow(InvalidArgumentException::class, 'does not exist');
    } finally {
        SourceSandbox::remove($root);
    }
});
