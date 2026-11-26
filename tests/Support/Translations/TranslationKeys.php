<?php

declare(strict_types=1);

namespace Tests\Support\Translations;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class TranslationKeys
{
    public const array FRAMEWORK_GROUPS = ['auth', 'validation'];

    /**
     * @return array<string, string>
     */
    public static function defined(string $locale): array
    {
        $keys = [];

        foreach (File::files(base_path('lang/' . $locale)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $lines = require $file->getPathname();

            if (is_array($lines)) {
                foreach (Arr::dot($lines, $file->getBasename('.php') . '.') as $key => $text) {
                    $keys[(string) $key] = is_string($text) ? $text : '';
                }
            }
        }

        $json = json_decode((string) file_get_contents(base_path("lang/{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

        foreach (is_array($json) ? $json : [] as $key => $text) {
            $keys[(string) $key] = is_string($text) ? $text : '';
        }

        return $keys;
    }

    /**
     * @return list<string>
     */
    public static function used(): array
    {
        $used = [];

        foreach (['app', 'bootstrap'] as $folder) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($folder), RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                    preg_match_all("/Translate::string\(\s*'([^']+)'/", (string) file_get_contents($file->getPathname()), $matches);
                    array_push($used, ...$matches[1]);
                }
            }
        }

        return array_values(array_unique($used));
    }

    /**
     * @return list<string>
     */
    public static function placeholders(string $text): array
    {
        preg_match_all('/:[a-z_]+/i', $text, $matches);
        $names = array_map(mb_strtolower(...), $matches[0]);
        sort($names);

        return $names;
    }
}
