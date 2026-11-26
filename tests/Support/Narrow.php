<?php

declare(strict_types=1);

namespace Tests\Support;

use LogicException;
use Stringable;

final class Narrow
{
    public static function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || $value === null || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new LogicException('The value is not a string: ' . get_debug_type($value));
    }

    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        if (! is_array($value)) {
            throw new LogicException('The value is not an array: ' . get_debug_type($value));
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        return array_values(array_map(self::string(...), self::map($value)));
    }

    /**
     * @return array<string, string>
     */
    public static function stringMap(mixed $value): array
    {
        return array_map(self::string(...), self::map($value));
    }
}
