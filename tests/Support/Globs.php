<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class Globs
{
    /**
     * @return list<string>
     */
    public static function of(string $pattern, int $flags = 0): array
    {
        $paths = glob($pattern, $flags);

        throw_if($paths === false, RuntimeException::class, 'The pattern could not be globbed: ' . $pattern);

        return $paths;
    }
}
