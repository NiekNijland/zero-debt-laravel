<?php

declare(strict_types=1);

namespace App\Support;

final class Translate
{
    /**
     * @param array<string, string|int> $replace
     */
    public static function string(string $key, array $replace = [], ?string $locale = null): string
    {
        $text = __($key, $replace, $locale);

        return is_string($text) ? $text : $key;
    }
}
