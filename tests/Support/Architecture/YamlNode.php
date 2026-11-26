<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class YamlNode
{
    /**
     * @return array<string, mixed>
     */
    public static function parse(string $yaml): array
    {
        try {
            return self::map(Yaml::parse($yaml));
        } catch (ParseException) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function map(mixed $node): array
    {
        $map = [];

        foreach (is_array($node) ? $node : [] as $key => $value) {
            $map[(string) $key] = $value;
        }

        return $map;
    }

    /**
     * @return list<mixed>
     */
    public static function items(mixed $node): array
    {
        return is_array($node) ? array_values($node) : [];
    }

    /**
     * @return list<string>
     */
    public static function texts(mixed $node): array
    {
        return array_values(array_filter(self::items($node), is_string(...)));
    }

    public static function text(mixed $node): string
    {
        return is_string($node) || is_int($node) ? (string) $node : '';
    }

    public static function at(mixed $node, string ...$path): mixed
    {
        foreach ($path as $key) {
            $map = self::map($node);

            if (! array_key_exists($key, $map)) {
                return null;
            }

            $node = $map[$key];
        }

        return $node;
    }

    public static function has(mixed $node, string $key, string ...$deeper): bool
    {
        $path = [$key, ...$deeper];
        $last = array_pop($path);

        return array_key_exists($last, self::map(self::at($node, ...$path)));
    }
}
