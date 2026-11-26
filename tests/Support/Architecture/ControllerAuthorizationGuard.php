<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class ControllerAuthorizationGuard
{
    private const string OWNERSHIP_CHECK = '/Gate\s*::|->\s*can\s*\(|->\s*cannot\s*\(|user_id|isOwnedBy|belongsToCustomer|getCurrentId|getKey\s*\(\s*\)/';

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
        $lines = [];

        foreach ($this->inspector->calls($source) as $call) {
            if (in_array($call['kind'], ['method', 'nullsafe', 'static'], true) && $call['name'] === 'authorize') {
                $lines[] = $call['line'];
            }

            if ($call['kind'] === 'function' && mb_ltrim(mb_strtolower($call['written']), '\\') === 'abort' && $this->denies($call['arguments'][0] ?? '')) {
                $lines[] = $call['line'];
            }

            if ($call['kind'] === 'function' && in_array(mb_strtolower($call['written']), ['abort_if', 'abort_unless'], true)
                && ($this->denies($call['arguments'][1] ?? '') || preg_match(self::OWNERSHIP_CHECK, implode(' ', $call['arguments'])) === 1)) {
                $lines[] = $call['line'];
            }
        }

        foreach (PhpCallReader::instantiations($source) as $instantiation) {
            $class = basename(str_replace('\\', '/', $instantiation['class']));

            if (in_array($class, ['AuthorizationException', 'AccessDeniedHttpException', 'UnauthorizedHttpException'], true) || ($class === 'HttpException' && $this->denies($instantiation['arguments'][0] ?? ''))) {
                $lines[] = $instantiation['line'];
            }
        }

        sort($lines);

        return array_map(
            static fn (int $line): string => 'line ' . $line . ': an action checks or refuses access itself: put the permission on the route, the rule about a model in its policy, and the rule about the input in the form request',
            $lines,
        );
    }

    private function denies(string $status): bool
    {
        $status = mb_trim($status, '() ');

        return in_array($status, ['401', '403'], true) || str_ends_with($status, 'HTTP_FORBIDDEN') || str_ends_with($status, 'HTTP_UNAUTHORIZED');
    }
}
