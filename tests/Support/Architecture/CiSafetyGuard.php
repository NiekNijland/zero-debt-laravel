<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class CiSafetyGuard
{
    public const array RULES = ['checks-script', 'base-branch', 'audit-production-only', 'read-only'];

    /**
     * @param list<string> $stages the commands of the checks script in the order they run: the base copy of the ratchets, the ratchets, the dashboard, the parallel checks
     * @param list<string> $requiredTasks the names of the tasks of the parallel call
     * @param array<string, string> $taskFlags a task name and the flag its command carries, so the check stays on the whole tree and blocks
     * @param list<string> $writingLines lines an owner exception allows to write
     */
    public function __construct(
        private string $baseBranch,
        private array $stages,
        private array $requiredTasks,
        private array $taskFlags,
        private string $dashboardFlags,
        private string $auditCommand,
        private array $writingLines = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function inChecks(string $checksScript, string $kitConfig): array
    {
        $lines = $this->code($checksScript);
        $text = implode("\n", $lines);

        return [
            ...$this->stageViolations($text),
            ...$this->taskViolations($text),
            ...$this->wholeTreeViolations($text),
            ...$this->auditViolations($text),
            ...$this->baseViolations($kitConfig),
            ...$this->inCommands($lines),
        ];
    }

    /**
     * @param list<string> $lines the command lines of a pipeline step or a script
     * @return list<string>
     */
    public function inCommands(array $lines): array
    {
        $violations = [];

        foreach ($lines as $line) {
            if (in_array($line, $this->writingLines, true)) {
                continue;
            }

            if (preg_match('/\bgit\s+(commit|push|add)\b/', $line) === 1) {
                $violations[] = $this->fail('read-only', 'a command writes to the repository: `' . $line . '` (CI checks and never commits, unless the owner kept an autofix commit: pass the line as an exception)');
            }

            if (preg_match('/\|\|\s*(true|:|exit\s+0)(?![A-Za-z0-9_])|continue-on-error|allow_failure/', $line) === 1) {
                $violations[] = $this->fail('checks-script', 'a check is made to succeed anyway: `' . $line . '`');
            }
        }

        return $violations;
    }

    private function command(string $text, string $task): ?string
    {
        return preg_match('/"' . preg_quote($task, '/') . '::([^"]*)"/', $text, $match) === 1 ? $match[1] : null;
    }

    /**
     * @return list<string>
     */
    private function code(string $script): array
    {
        return array_values(array_filter(
            array_map(static fn (string $line): string => mb_rtrim((string) preg_replace('/^\s+/', '', $line)), explode("\n", $script)),
            static fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'),
        ));
    }

    private function fail(string $rule, string $message): string
    {
        return '[' . $rule . '] ' . $message;
    }

    /**
     * @return list<string>
     */
    private function stageViolations(string $text): array
    {
        $positions = array_map(static fn (string $stage): int|false => mb_strpos($text, $stage), $this->stages);
        $found = array_filter($positions, is_int(...));
        $sorted = $found;
        sort($sorted);

        if (count($found) !== count($positions) || $found !== $sorted || $found !== array_unique($found)) {
            return [$this->fail('checks-script', 'the checks script does not run its stages in the order: ' . implode(', then ', $this->stages))];
        }

        $violations = [];

        if (preg_match('/debt-dashboard\.sh[^\n]*' . preg_quote($this->dashboardFlags, '/') . '/', $text) !== 1) {
            $violations[] = $this->fail('checks-script', 'the dashboard line of the checks script does not run with ' . $this->dashboardFlags);
        }

        return [...$violations, ...(preg_match('/<[a-z][a-z0-9 ,.:()\/-]*>/', $text) === 1 ? [$this->fail('checks-script', "the checks script still holds a <placeholder>: replace it with the project's command")] : [])];
    }

    /**
     * @return list<string>
     */
    private function taskViolations(string $text): array
    {
        $violations = [];

        foreach ($this->requiredTasks as $task) {
            if (! str_contains($text, '"' . $task . '::')) {
                $violations[] = $this->fail('checks-script', 'the checks script has no task named "' . $task . '": a check that is no entry of the parallel call does not run');
            }
        }

        foreach ($this->taskFlags as $task => $flag) {
            $command = $this->command($text, $task);

            if ($command !== null && ! str_contains($command, $flag)) {
                $violations[] = $this->fail('checks-script', 'the task "' . $task . '" runs without `' . $flag . '`: it must check the whole tree and block');
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function wholeTreeViolations(string $text): array
    {
        $violations = [];

        foreach (explode("\n", $text) as $line) {
            if (preg_match('/git\s+diff\b[^"]*--name-(only|status)|--changed\b|--dirty\b|--diff=|git\s+ls-files\s+-m/', $line) === 1 && ! str_contains($line, 'mutation')) {
                $violations[] = $this->fail('checks-script', 'a command of the checks script takes a list of changed files: `' . mb_trim($line) . '` (every check names the whole tree)');
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function auditViolations(string $text): array
    {
        $audits = array_filter(explode("\n", $text), fn (string $line): bool => str_contains($line, $this->auditCommand));

        if ($audits === []) {
            return [$this->fail('audit-production-only', 'the checks script has no audit: ' . $this->auditCommand)];
        }

        $production = array_filter($audits, static fn (string $line): bool => str_contains($line, '--no-dev'));

        return count($production) === count($audits) || $production === []
            ? [$this->fail('audit-production-only', 'the audit of a deploying branch covers production dependencies only (--no-dev), and the audit of a pull request covers everything: the checks script has ' . ($production === [] ? 'no production-only audit' : 'only production-only audits'))]
            : [];
    }

    /**
     * @return list<string>
     */
    private function baseViolations(string $kitConfig): array
    {
        if (preg_match('/^DEFAULT_BASE_BRANCH="([^"]*)"/m', $kitConfig, $match) !== 1 || $match[1] !== $this->baseBranch) {
            return [$this->fail('base-branch', 'DEFAULT_BASE_BRANCH of the shared config is not `' . $this->baseBranch . '`, the branch pull requests target')];
        }

        return [];
    }
}
