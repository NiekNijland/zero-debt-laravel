<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Tests\Support\Globs;

final readonly class ModuleOverlapGuard
{
    public function __construct(
        private string $root,
        private string $modulesFolder,
    ) {
    }

    /**
     * @return list<string>
     */
    public function modules(): array
    {
        $folders = Globs::of($this->root . '/' . $this->modulesFolder . '/*', GLOB_ONLYDIR);

        return array_map(basename(...), $folders);
    }

    /**
     * @param list<string> $modules
     * @return list<string>
     */
    public function inModules(array $modules): array
    {
        $offenders = [];

        foreach ($modules as $module) {
            foreach ($modules as $other) {
                if ($module === $other) {
                    continue;
                }

                $stems = array_unique([$other, str_ends_with($other, 's') ? mb_substr($other, 0, -1) : $other]);
                $plurals = [$other . 's', str_ends_with($other, 'y') ? mb_substr($other, 0, -1) . 'ies' : $other . 's'];
                $prefixed = array_filter($stems, static fn (string $stem): bool => preg_match('/^' . preg_quote($stem, '/') . '[A-Z]/', $module) === 1);

                if ($prefixed !== [] || in_array($module, $plurals, true)) {
                    $offenders[] = $module . ' overlaps ' . $other . ': one module per domain: fold ' . $module . ' into ' . $other . ', or name it for a different domain';
                }
            }
        }

        return $offenders;
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return $this->inModules($this->modules());
    }
}
