<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class ConfigAccessGuard
{
    /**
     * @param list<string> $folders
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $folders,
        private string $facade,
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
        foreach ($this->inspector->calls($source) as $call) {
            $written = mb_ltrim($call['written'], '\\');
            $untyped = ($call['kind'] === 'function' && ! str_contains($written, '\\') && mb_strtolower($written) === 'config')
                || ($call['kind'] === 'static' && in_array($written, ['Config', $this->facade], true) && $call['name'] === 'get')
                || ($call['kind'] === 'method' && $call['name'] === 'get' && $call['receiver'] === '->config');

            if ($untyped) {
                return ['line ' . $call['line'] . ': config is read untyped: read it through a typed accessor (Config::string(), Config::integer(), Config::boolean(), Config::array()) so a wrong type fails where it is read'];
            }
        }

        return [];
    }
}
