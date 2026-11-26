<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final class DatabaseEngine
{
    public static function of(string $image): string
    {
        $name = mb_strtolower((string) preg_replace('/[:@].*$/', '', basename($image)));

        return match (true) {
            str_contains($name, 'mariadb') => 'mariadb',
            str_contains($name, 'mysql') => 'mysql',
            str_contains($name, 'postgres'), str_contains($name, 'postgis') => 'postgres',
            str_contains($name, 'mongo') => 'mongo',
            str_contains($name, 'redis') => 'redis',
            default => '',
        };
    }

    public static function needsPassword(string $engine): bool
    {
        return ! in_array($engine, ['mongo', 'redis'], true);
    }

    /**
     * @return list<string> the variables of which the service must set one
     */
    public static function passwordKeys(string $engine): array
    {
        return match ($engine) {
            'postgres' => ['POSTGRES_PASSWORD', 'POSTGRES_HOST_AUTH_METHOD'],
            'mysql' => ['MYSQL_ROOT_PASSWORD', 'MYSQL_ALLOW_EMPTY_PASSWORD', 'MYSQL_RANDOM_ROOT_PASSWORD'],
            'mariadb' => ['MARIADB_ROOT_PASSWORD', 'MARIADB_ALLOW_EMPTY_ROOT_PASSWORD', 'MARIADB_RANDOM_ROOT_PASSWORD', 'MYSQL_ROOT_PASSWORD'],
            default => [],
        };
    }

    /**
     * @return list<string> the tools of which the health command must use one
     */
    public static function healthTools(string $engine): array
    {
        return match ($engine) {
            'postgres' => ['pg_isready'],
            'mysql' => ['mysqladmin'],
            'mariadb' => ['healthcheck.sh', 'mariadb-admin', 'mysqladmin'],
            'mongo' => ['mongosh', 'mongo '],
            'redis' => ['redis-cli'],
            default => [],
        };
    }
}
