<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final class GithubWorkflowReader
{
    public const string CHECKS = 'bash scripts/checks.sh';

    public const string EQUALS = '=' . '=';

    private const array EVENTS = ['pull_request', 'push', 'schedule'];

    public static function cancelsEveryEvent(mixed $cancel): bool
    {
        return $cancel === true || (is_string($cancel) && ! str_contains($cancel, 'pull_request'));
    }

    public static function writes(mixed $permissions): bool
    {
        return is_string($permissions) ? str_starts_with($permissions, 'write') : (is_array($permissions) && in_array('write', $permissions, true));
    }

    /**
     * @param array<mixed> $workflow
     * @return array<mixed>
     */
    public static function triggers(array $workflow): array
    {
        $on = $workflow['on'] ?? $workflow[1] ?? [];

        if (is_string($on)) {
            return [$on => null];
        }

        return is_array($on) && array_is_list($on) ? array_fill_keys(self::texts($on), null) : (is_array($on) ? $on : []);
    }

    /**
     * @param array<mixed> $workflow
     * @return array<string, array<mixed>>
     */
    public static function jobs(array $workflow): array
    {
        $jobs = [];

        foreach (is_array($workflow['jobs'] ?? null) ? $workflow['jobs'] : [] as $id => $job) {
            if (is_array($job)) {
                $jobs[(string) $id] = $job;
            }
        }

        return $jobs;
    }

    /**
     * @param array<mixed> $workflow
     */
    public static function runsChecks(array $workflow): bool
    {
        return array_any(self::jobs($workflow), static fn (array $job): bool => array_any(self::lines($job), static fn (string $line): bool => str_starts_with($line, GithubWorkflowReader::CHECKS)));
    }

    /**
     * @param array<mixed> $job
     */
    public static function condition(array $job): string
    {
        return mb_trim(self::text($job['if'] ?? ''));
    }

    /**
     * @return list<string>
     */
    public static function events(mixed $condition): array
    {
        if (! is_string($condition)) {
            return self::EVENTS;
        }

        if (preg_match('/^github\.event_name \={2} \'(\w+)\'$/', mb_trim($condition), $match) === 1) {
            return [$match[1]];
        }

        if (preg_match('/^github\.event_name \!\= \'(\w+)\'$/', mb_trim($condition), $match) === 1) {
            return array_values(array_diff(self::EVENTS, [$match[1]]));
        }

        return self::EVENTS;
    }

    /**
     * @param array<mixed> $job
     * @return list<string>
     */
    public static function lines(array $job): array
    {
        $lines = [];

        foreach (is_array($job['steps'] ?? null) ? $job['steps'] : [] as $step) {
            if (is_array($step) && is_string($step['run'] ?? null)) {
                array_push($lines, ...array_values(array_filter(array_map(trim(...), explode("\n", $step['run'])), static fn (string $line): bool => $line !== '')));
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        $strings = [];

        foreach (is_array($value) ? $value : [] as $item) {
            array_push($strings, ...self::strings($item));
        }

        return $strings;
    }

    /**
     * @param array<mixed> $job
     * @return list<string>
     */
    public static function needs(array $job): array
    {
        $needs = $job['needs'] ?? [];

        return array_values(array_filter(is_array($needs) ? $needs : [$needs], is_string(...)));
    }

    /**
     * @param array<mixed> $job
     * @return list<array<mixed>>
     */
    public static function steps(array $job): array
    {
        return array_values(array_filter(is_array($job['steps'] ?? null) ? $job['steps'] : [], is_array(...)));
    }

    public static function truthy(mixed $value): bool
    {
        return (bool) $value;
    }

    public static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<string>
     */
    public static function texts(mixed $value): array
    {
        return array_values(array_map(self::text(...), is_array($value) ? $value : []));
    }

    public static function at(mixed $node, string ...$path): mixed
    {
        foreach ($path as $key) {
            if (! is_array($node) || ! array_key_exists($key, $node)) {
                return null;
            }

            $node = $node[$key];
        }

        return $node;
    }

    /**
     * @param array<mixed> $job
     * @return array<mixed>
     */
    public static function checkout(array $job): array
    {
        foreach (is_array($job['steps'] ?? null) ? $job['steps'] : [] as $step) {
            if (is_array($step) && str_starts_with(self::text($step['uses'] ?? ''), 'actions/checkout@')) {
                return $step;
            }
        }

        return [];
    }
}
