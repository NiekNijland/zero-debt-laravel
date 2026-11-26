<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Filesystem\Filesystem;

final class SourceSandbox
{
    /**
     * @param array<string, string> $files
     */
    public static function create(array $files, string $prefix = 'source-sandbox-'): string
    {
        $root = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(6));

        foreach ($files as $path => $contents) {
            $absolute = $root . '/' . $path;

            if (! is_dir(dirname($absolute))) {
                mkdir(dirname($absolute), 0777, true);
            }

            file_put_contents($absolute, $contents);
        }

        return $root;
    }

    public static function remove(string $root): void
    {
        foreach (RecursiveFiles::in($root, childFirst: true) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : new Filesystem()->delete($entry->getPathname());
        }

        rmdir($root);
    }
}
