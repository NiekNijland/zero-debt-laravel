<?php

declare(strict_types=1);

namespace Tests\Support\Unit;

use Tests\Support\Architecture\DateCastGuard;
use Tests\Support\Architecture\PhpFileInspector;
use Tests\Support\Architecture\RoleNameGuard;

final class PhpFileInspectorHelpers
{
    public static function checksRoleName(string $source): bool
    {
        return new RoleNameGuard(new PhpFileInspector(), sys_get_temp_dir(), [])->inSource($source) !== [];
    }

    public static function castsDateMutably(string $source): bool
    {
        return new DateCastGuard(new PhpFileInspector(), sys_get_temp_dir(), '')->inSource($source) !== [];
    }

    /**
     * @return list<string>
     */
    public static function summarisedCalls(string $source): array
    {
        return array_map(
            static fn (array $call): string => implode('|', [$call['kind'], $call['line'], $call['written'] !== '' ? $call['written'] : $call['name'], $call['receiver'] ?? '-']),
            new PhpFileInspector()->calls($source),
        );
    }
}
