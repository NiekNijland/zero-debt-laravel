<?php

declare(strict_types=1);

use Tests\Support\RecursiveFiles;
use Tests\Support\SourceSandbox;

uses()->group('arch');

it('walks the files of a tree, leaves only, and the folders after their children when asked', function (): void {
    $root = SourceSandbox::create(['a.txt' => 'x', 'dir/b.txt' => 'x', 'dir/deep/c.txt' => 'x']);

    try {
        $leaves = array_map(static fn (SplFileInfo $entry): string => mb_substr($entry->getPathname(), mb_strlen($root) + 1), iterator_to_array(RecursiveFiles::in($root), false));
        $all = array_map(static fn (SplFileInfo $entry): string => mb_substr($entry->getPathname(), mb_strlen($root) + 1), iterator_to_array(RecursiveFiles::in($root, childFirst: true), false));

        sort($leaves);

        $position = array_flip($all);

        expect($leaves)->toBe(['a.txt', 'dir/b.txt', 'dir/deep/c.txt'])
            ->and($all)->toHaveCount(5)
            ->and($position['dir/deep/c.txt'] < $position['dir/deep'])->toBeTrue()
            ->and($position['dir/deep'] < $position['dir'])->toBeTrue();
    } finally {
        SourceSandbox::remove($root);
    }
});
