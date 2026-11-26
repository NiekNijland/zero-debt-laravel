<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class CiSafetyGuard
{
    public const string PLACEHOLDER = '/<[a-z][a-z0-9 ,.:()\/-]*>/';

    private const array REQUIRED_TASKS = ['Format', 'Refactor', 'Analyser', 'Type coverage', 'Lint', 'Guard tests', 'Audit', 'Commit messages', 'Secret scan'];

    /**
     * @param list<string> $writingLines lines an owner exception allows to write (an autofix commit the owner kept in step 1)
     * @param string $zeroWarnings the linter's flag for zero warnings allowed (point 5), as the project's linter spells it
     */
    public function __construct(
        private string $baseBranch,
        private array $writingLines = [],
        private string $zeroWarnings = '--max-warnings 0',
    ) {
    }

    /**
     * @return list<string>
     */
    public function inChecks(string $checksScript, string $kitConfig): array
    {
        $lines = $this->code($checksScript);
        $text = implode("\n", $lines);
        $violations = [];

        $positions = array_map(static fn (string $part): int|false => mb_strpos($text, $part), ['--guard scripts/ci-ratchets.sh', 'bash scripts/ci-ratchets.sh', 'debt-dashboard.sh $DASHBOARD_MODE --offline', 'bash scripts/ci-parallel.sh']);

        if (in_array(false, $positions, true) || $positions !== array_values(array_unique($positions)) || $positions !== $this->sorted($positions)) {
            $violations[] = 'checks.sh does not run its four stages in the order: the base copy of the ratchets with --guard, the ratchets, the dashboard, the parallel checks';
        }

        if (preg_match('/debt-dashboard\.sh[^\n]*--offline --details/', $text) !== 1) {
            $violations[] = 'the dashboard line of checks.sh does not run with --offline --details';
        }

        foreach (self::REQUIRED_TASKS as $task) {
            if (! str_contains($text, '"' . $task . '::')) {
                $violations[] = 'checks.sh has no task named "' . $task . '": a check that is no entry of the parallel call does not run';
            }
        }

        if (preg_match(self::PLACEHOLDER, $text) === 1) {
            $violations[] = "checks.sh still holds a <placeholder>: replace it with the project's command";
        }

        $violations = [...$violations, ...$this->wholeTree($text), ...$this->auditRules($text)];

        if (preg_match('/^DEFAULT_BASE_BRANCH="([^"]*)"/m', $kitConfig, $match) !== 1 || $match[1] !== $this->baseBranch) {
            $violations[] = 'DEFAULT_BASE_BRANCH in kit-config.sh is not `' . $this->baseBranch . '`, the branch pull requests target';
        }

        return [...$violations, ...$this->inCommands($lines)];
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
                $violations[] = 'a command writes to the repository: `' . $line . '` (CI checks and never commits, unless the owner kept an autofix commit: pass the line as an exception)';
            }

            if (preg_match('/\|\|\s*(true|:|exit\s+0)(?![A-Za-z0-9_])|continue-on-error|allow_failure/', $line) === 1) {
                $violations[] = 'a check is made to succeed anyway: `' . $line . '`';
            }

            if (preg_match('/\bcomposer\s+(update|require)\b|\bnpm\s+(install|i)\b(?!.*(\s-g\b|--global))|\byarn\s+install\b(?!.*(--frozen-lockfile|--immutable))|\bpnpm\s+(install|i)\b(?!.*--frozen-lockfile)/', $line) === 1) {
                $violations[] = 'an install that does not come from the lock file: `' . $line . '` (composer install, npm ci, yarn install --frozen-lockfile, pnpm install --frozen-lockfile)';
            }

            if (preg_match('/\beslint\b.*--cache\b|--cache-location/', $line) === 1) {
                $violations[] = 'the type-aware lint run has a content cache: `' . $line . '` (a per-file result hides a new violation in an unchanged file)';
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function auditRules(string $text): array
    {
        $production = '/--omit=dev|--no-dev|--production\b|--prod\b/';

        if (preg_match('/"\$RUN" = "--main" \]; then\s+TASKS\+=\(\s+"Audit::([^"]*)"\s+\)\s+else\s+TASKS\+=\(\s+"Audit::([^"]*)"/', $text, $audit) !== 1) {
            return ['checks.sh has no separate "Audit" task for --main and for the pull request: a deploying branch audits production dependencies only'];
        }

        return [
            ...(preg_match($production, $audit[1]) === 1 ? [] : ['the "Audit" task of --main does not cover production dependencies only (`--omit=dev`, `--no-dev`): an advisory in a development tool would stop a hotfix']),
            ...(preg_match($production, $audit[2]) === 1 ? ['the "Audit" task of a pull request covers production dependencies only: the full audit runs there'] : []),
        ];
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

    /**
     * @param list<int|false> $positions
     * @return list<int|false>
     */
    private function sorted(array $positions): array
    {
        $copy = $positions;
        sort($copy);

        return $copy;
    }

    /**
     * @return list<string>
     */
    private function wholeTree(string $text): array
    {
        $violations = [];

        if (preg_match('/--changed\b|--dirty\b|--since\b|\bgit\s+diff\b|CHANGED_FILES/', $text) === 1) {
            $violations[] = 'a command of checks.sh takes a list of changed files or asks version control for a diff: every check names the whole tree (the mutation run of a pull request is the one exception, and it is not in this script). A drift check of a generated file generates it to a temporary path and compares by content: cmp -s <committed file> "$tmp"';
        }

        if (preg_match('/"Lint::([^"]*)"/', $text, $lint) === 1 && ! str_contains($lint[1], $this->zeroWarnings)) {
            $violations[] = 'the "Lint" task does not run with `' . $this->zeroWarnings . '`: a warning the linter may print is a warning that stays';
        }

        return $violations;
    }
}
