<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final class GithubRunnerRules
{
    private const string SETUP_PHP = 'shivammathur/setup-php@';

    private const string SETUP_NODE = 'actions/setup-node@';

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    public static function violations(array $workflow, bool $frontend): array
    {
        $violations = [];

        foreach (GithubWorkflowReader::jobs($workflow) as $id => $job) {
            if (GithubWorkflowReader::truthy($job['container'] ?? null) || isset($job['uses'])) {
                continue;
            }

            $lines = GithubWorkflowReader::lines($job);
            $uses = array_map(static fn (mixed $step): string => is_array($step) && is_string($step['uses'] ?? null) ? $step['uses'] : '', is_array($job['steps'] ?? null) ? $job['steps'] : []);
            $runsChecks = array_any($lines, static fn (string $line): bool => str_starts_with($line, GithubWorkflowReader::CHECKS) && ! str_starts_with($line, GithubWorkflowReader::CHECKS . ' --coverage '));
            $php = $runsChecks || array_any($lines, static fn (string $line): bool => preg_match('/\b(composer|php|phpunit|pest)\b/', $line) === 1);
            $node = array_any($lines, static fn (string $line): bool => preg_match('/\b(npm|npx|node|yarn|pnpm)\b/', $line) === 1) || ($frontend && $runsChecks);

            if ($php && ! array_any($uses, static fn (string $use): bool => str_starts_with($use, self::SETUP_PHP))) {
                $violations[] = 'job `' . $id . '` runs on the runner and runs PHP tools but has no `' . self::SETUP_PHP . '<sha>` step: the runner image decides the PHP version and its extensions, and the baselines move with it';
            }

            if ($node && ! array_any($uses, static fn (string $use): bool => str_starts_with($use, self::SETUP_NODE))) {
                $violations[] = 'job `' . $id . '` runs on the runner and runs Node tools but has no `' . self::SETUP_NODE . '<sha>` step: the runner image decides the Node version';
            }
        }

        return $violations;
    }
}
