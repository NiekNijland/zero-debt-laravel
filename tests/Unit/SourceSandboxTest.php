<?php

declare(strict_types=1);

use Tests\Support\SourceSandbox;

uses()->group('arch');

it('writes the files of a tree and removes the whole tree', function (): void {
    $root = SourceSandbox::create(['a.php' => '<?php', 'x/y/b.php' => '<?php return 1;']);

    expect(file_get_contents($root . '/x/y/b.php'))->toBe('<?php return 1;')
        ->and(is_file($root . '/a.php'))->toBeTrue();

    SourceSandbox::remove($root);

    expect(file_exists($root))->toBeFalse();
});

it('names the folder with the prefix, so a dot directory can be tested', function (): void {
    $root = SourceSandbox::create(['a.php' => '<?php'], prefix: '.checkout-');

    try {
        expect(basename($root))->toStartWith('.checkout-');
    } finally {
        SourceSandbox::remove($root);
    }
});
