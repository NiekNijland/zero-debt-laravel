<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class RoleNameGuard
{
    private const string MESSAGE = 'a role name is checked: check a permission with can() or a permission middleware, because a role is data that an administrator edits';

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
        $roleChecks = ['hasrole', 'hasanyrole', 'hasallroles', 'hasexactroles'];

        return TemporarySource::with($source, function (string $file) use ($roleChecks): array {
            $checks = array_any($this->inspector->callsIn($file), static fn (array $call): bool => in_array($call['kind'], ['method', 'nullsafe'], true) && in_array($call['name'], $roleChecks, true))
                || array_any($this->inspector->stringLiterals($file), static fn (string $value): bool => preg_match('/(^|\|)(role|role_or_permission):/', $value) === 1);

            return $checks ? [self::MESSAGE] : [];
        });
    }
}
