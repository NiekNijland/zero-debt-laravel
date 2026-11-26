<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class GlobalImportGuard
{
    private const string MESSAGE = 'imports a global class by its bare name in a file without a namespace, which PHP ignores: delete the use line and write the class as it is, or declare a namespace';

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
    public function inSource(string $source): array
    {
        if (preg_match('/^namespace\s/m', $source) === 1) {
            return [];
        }

        return preg_match('/^use\s+[\\\\]?[A-Za-z_]+\s*(,\s*[\\\\]?[A-Za-z_]+\s*)*;\s*$/m', $source) === 1 ? [self::MESSAGE] : [];
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = FolderScan::over($this->inspector, $this->root, $this->folders, fn (string $path, string $source): array => $this->inSource($source));

        sort($violations);

        return $violations;
    }
}
