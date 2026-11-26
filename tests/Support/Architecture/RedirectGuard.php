<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class RedirectGuard
{
    /**
     * @param list<string> $folders
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $folders,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return FolderScan::over($this->inspector, $this->root, $this->folders, fn (string $path, string $source): array => $this->inSource($source));
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $lines = [];

        foreach ($this->inspector->calls($source) as $call) {
            if ($call['kind'] === 'method' && in_array($call['name'], ['route', 'back'], true) && $call['receiver'] === 'redirect()') {
                $lines[] = $call['classLine'];
            }

            if ($call['kind'] === 'static' && in_array($call['name'], ['route', 'back'], true) && mb_strtolower(basename(str_replace('\\', '/', $call['class'] ?? ''))) === 'redirect') {
                $lines[] = $call['classLine'];
            }
        }

        sort($lines);

        return array_map(
            static fn (int $line): string => 'line ' . $line . ": a redirect through redirect(): write to_route('name') for a named route and back() to go back",
            $lines,
        );
    }
}
