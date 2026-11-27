<?php

declare(strict_types=1);

namespace Tests\Support;

final class ModuleNamespaces
{
    /**
     * @return list<string>
     */
    public static function of(string $folder, ?string $root = null): array
    {
        $directories = Globs::of(($root ?? dirname(__DIR__, 2)) . '/app/Modules/*/' . $folder, GLOB_ONLYDIR);

        return array_map(static fn (string $directory): string => 'App\Modules\\' . basename(dirname($directory)) . '\\' . $folder, $directories);
    }
}
