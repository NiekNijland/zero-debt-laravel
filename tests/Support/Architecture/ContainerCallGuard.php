<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

final readonly class ContainerCallGuard
{
    /**
     * @param list<string> $exemptPrefixes
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
        private array $exemptPrefixes,
        private string $facade,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return FolderScan::over(
            $this->inspector,
            $this->root,
            [$this->folder],
            fn (string $path, string $source): array => array_any($this->exemptPrefixes, static fn (string $prefix): bool => str_starts_with($path, $prefix)) ? [] : $this->inSource($source),
        );
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $messages = [];
        $statements = PhpSource::parse($source);
        $methods = new NodeFinder()->findInstanceOf($statements, ClassMethod::class);

        foreach ($this->lines($source) as $line) {
            $method = array_find($methods, static fn (ClassMethod $candidate): bool => $candidate->getStartLine() <= $line && $line <= $candidate->getEndLine());

            $messages[] = ($method?->name->toString() ?? 'outside a method') . ' line ' . $line . ': the container is called by hand: take the dependency as a constructor argument, or as an argument of handle()';
        }

        return $messages;
    }

    /**
     * @return list<int>
     */
    private function lines(string $source): array
    {
        $lines = [];

        foreach ($this->inspector->calls($source) as $call) {
            $written = mb_ltrim($call['written'], '\\');

            if ($call['kind'] === 'function' && ! str_contains($written, '\\') && in_array(mb_strtolower($written), ['app', 'resolve'], true)) {
                $lines[] = $call['line'];
            }

            if ($call['kind'] === 'static' && in_array($written, ['App', $this->facade], true) && in_array($call['name'], ['make', 'makewith', 'call', 'resolve'], true)) {
                $lines[] = $call['classLine'];
            }

            if ($call['kind'] === 'method' && in_array($call['name'], ['make', 'makewith', 'call', 'resolve'], true) && in_array($call['receiver'], ['->container', '->app', '$container', '$app'], true)) {
                $lines[] = $call['line'];
            }

            if ($call['kind'] === 'static' && $written === 'Container' && $call['name'] === 'getinstance') {
                $lines[] = $call['classLine'];
            }
        }

        sort($lines);

        return $lines;
    }
}
