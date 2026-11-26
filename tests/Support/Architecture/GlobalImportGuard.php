<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;

final readonly class GlobalImportGuard
{
    private const string MESSAGE = 'imports a global class by its bare name in a file without a namespace, which PHP ignores: delete the use line and write the class as it is, or declare a namespace';

    /**
     * @param list<string> $folders folders under the root that the tree scan reads, relative to it
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $folders,
    ) {
    }

    /**
     * @return list<string> the messages for the source: none, or one
     */
    public function inSource(string $source): array
    {
        $statements = PhpSource::parse($source);
        $scope = self::globalScope($statements);

        foreach ($scope as $statement) {
            if ($statement instanceof Use_ && $this->hasBareName($statement)) {
                return [self::MESSAGE];
            }
        }

        return [];
    }

    /**
     * @return list<string> `path: message` per file
     */
    public function violations(): array
    {
        $violations = FolderScan::over($this->inspector, $this->root, $this->folders, fn (string $path, string $source): array => $this->inSource($source));

        sort($violations);

        return $violations;
    }

    /**
     * @param array<Stmt> $statements
     * @return array<Stmt>
     */
    private function globalScope(array $statements): array
    {
        $scope = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Namespace_) {
                if (! $statement->name instanceof Name) {
                    array_push($scope, ...$statement->stmts);
                }

                continue;
            }

            $scope[] = $statement;
        }

        return array_values(array_filter($statements, static fn (Stmt $statement): bool => $statement instanceof Namespace_ && $statement->name instanceof Name)) === [] ? $scope : [];
    }

    private function hasBareName(Use_ $statement): bool
    {
        return array_any($statement->uses, fn (UseItem $item): bool => count($item->name->getParts()) === 1 && ! $item->alias instanceof Identifier);
    }
}
