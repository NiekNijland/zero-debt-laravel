<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class AnalyserPathsGuard
{
    public const array RULES = ['analyser-paths'];

    /**
     * @param list<string> $folders every folder of the source and test paths of the ratchet script, and the public entry folder
     * @param list<string> $includes the bleeding-edge file and every extension, named as the config includes them
     */
    public function __construct(
        private array $folders,
        private array $includes,
    ) {
    }

    /**
     * @param list<string> $configFiles
     * @return list<string>
     */
    public function violations(array $configFiles): array
    {
        return $this->inConfigs(array_map(static fn (string $file): string => (string) file_get_contents($file), $configFiles));
    }

    /**
     * @param list<string> $configs the texts of the analyser configs, which the run reads together
     * @return list<string>
     */
    public function inConfigs(array $configs): array
    {
        $paths = array_merge(...array_map($this->paths(...), $configs));
        $violations = [];

        foreach ($this->folders as $folder) {
            if (! array_any($paths, static fn (string $path): bool => $path === $folder || str_starts_with($path, $folder . '/'))) {
                $violations[] = '[analyser-paths] the analyser paths do not name `' . $folder . '`: a folder the analyser does not read has no type check';
            }
        }

        foreach ($this->includes as $include) {
            if (! array_any($configs, static fn (string $config): bool => str_contains($config, $include))) {
                $violations[] = '[analyser-paths] the analyser config does not include `' . $include . '`: the bleeding-edge file and every extension are named';
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function paths(string $config): array
    {
        $paths = [];
        $inside = false;

        foreach (explode("\n", $config) as $line) {
            if (preg_match('/^(\s*)paths:\s*$/', $line) === 1) {
                $inside = true;

                continue;
            }

            if ($inside && preg_match('/^\s*-\s+(\S+)\s*$/', $line, $match) === 1) {
                $paths[] = mb_trim($match[1], '\'"');

                continue;
            }

            $inside = $inside && mb_trim($line) === '';
        }

        return $paths;
    }
}
