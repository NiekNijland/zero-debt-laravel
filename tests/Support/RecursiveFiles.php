<?php

declare(strict_types=1);

namespace Tests\Support;

use FilesystemIterator;
use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class RecursiveFiles
{
    /**
     * @return Generator<int, SplFileInfo>
     */
    public static function in(string $directory, bool $childFirst = false): Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            $childFirst ? RecursiveIteratorIterator::CHILD_FIRST : RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $entry) {
            if ($entry instanceof SplFileInfo) {
                yield $entry;
            }
        }
    }
}
