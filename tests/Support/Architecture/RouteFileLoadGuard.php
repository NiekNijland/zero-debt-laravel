<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class RouteFileLoadGuard
{
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return FolderScan::over($this->inspector, $this->root, [$this->folder], fn (string $path, string $source): array => $this->inSource($source));
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        foreach ($this->inspector->calls($source) as $call) {
            if ($call['name'] === 'loadroutesfrom') {
                return ['line ' . $call['line'] . ': a class loads a route file: register routes in the route files and in the bootstrap file, which is where the route list is read'];
            }
        }

        return [];
    }
}
