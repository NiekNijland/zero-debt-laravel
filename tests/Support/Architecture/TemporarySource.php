<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Filesystem\Filesystem;

final class TemporarySource
{
    /**
     * @template T
     *
     * @param callable(string): T $read
     * @return T
     */
    public static function with(string $source, callable $read): mixed
    {
        $path = sys_get_temp_dir() . '/source-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($path, $source);

        try {
            return $read($path);
        } finally {
            new Filesystem()->delete($path);
        }
    }
}
