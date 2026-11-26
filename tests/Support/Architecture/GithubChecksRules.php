<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class GithubChecksRules
{
    public const string PART_LINE = 'bash scripts/checks.sh --part ${{ matrix.part }}';

    public const string MAIN_LINE = 'bash scripts/checks.sh --main --part ${{ matrix.part }}';

    public const string MUTATION_LINE = 'bash scripts/mutation.sh';

    private const string AGGREGATE_LINE = 'test "$RESULT" = success';

    /**
     * @param list<string> $parts the parts of the checks step, read from the checks script
     * @param string $databaseImage DATABASE_IMAGE of the kit config; empty where the project has no database
     * @param bool $frontend whether the project has a frontend: a `frontend-tests` job and a browser in the smoke run
     * @param string $smokeBrowser the text that the smoke job's command holds when it runs in a browser (the browser group)
     * @param string $backendTestScript the text of the script behind the backend test command (a Composer script), where the coverage line is in it and not in the job; empty when the job runs the line itself
     */
    public function __construct(
        private array $parts,
        private string $backendTestCommand,
        private string $databaseImage,
        private bool $frontend,
        private string $smokeBrowser,
        private string $backendTestScript = '',
    ) {
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    public function violations(array $workflow): array
    {
        $jobs = GithubWorkflowReader::jobs($workflow);
        $violations = [];

        foreach ([self::PART_LINE => 'pull_request', self::MAIN_LINE => 'push'] as $line => $event) {
            $carrying = array_filter($jobs, static fn (array $job): bool => in_array($line, GithubWorkflowReader::lines($job), true));

            if ($carrying === []) {
                $violations[] = 'no job runs exactly `' . $line . '`: the ' . ($event === 'push' ? 'checks of a deploying branch' : 'checks of a pull request') . ' are missing';
            }

            foreach ($carrying as $id => $job) {
                $violations = [...$violations, ...$this->checksJobRules($id, $job, $event)];
            }
        }

        return [...$violations, ...$this->aggregateRules($jobs), ...$this->testRules($jobs), ...$this->scheduledRules($jobs, $this->crons($workflow))];
    }

    /**
     * @param array<mixed> $step
     * @return list<string>
     */
    private function checksStepRules(string $id, array $step): array
    {
        $violations = [];

        if (isset($step['if'])) {
            $violations[] = 'the checks step of job `' . $id . '` has a condition of its own';
        }

        foreach (['shell', 'working-directory'] as $key) {
            if (isset($step[$key])) {
                $violations[] = 'the checks step of job `' . $id . '` sets `' . $key . '`: nothing replaces the line, its shell or its folder';
            }
        }

        foreach (['CHECK_BASE', 'CHECK_HEAD', 'DASHBOARD_MODE'] as $key) {
            if (is_array($step['env'] ?? null) && array_key_exists($key, $step['env'])) {
                $violations[] = 'the checks step of job `' . $id . '` sets ' . $key . ' in its own env: it would override the base the job hands to the ratchets';
            }
        }

        return $violations;
    }

    /**
     * @param array<string, array<mixed>> $jobs
     * @return list<string>
     */
    private function aggregateRules(array $jobs): array
    {
        $aggregates = array_filter($jobs, static fn (array $job): bool => in_array(self::AGGREGATE_LINE, GithubWorkflowReader::lines($job), true));
        $checks = array_keys(array_filter($jobs, static fn (array $job): bool => in_array(self::PART_LINE, GithubWorkflowReader::lines($job), true)));
        $violations = [];

        if ($aggregates === []) {
            return ['no aggregate job: a job with `needs`, `if: always()` and the step `' . self::AGGREGATE_LINE . '` is the one required status check that follows PARTS'];
        }

        foreach ($aggregates as $id => $job) {
            if (! str_contains(GithubWorkflowReader::condition($job), 'always()') || GithubWorkflowReader::needs($job) !== array_map(strval(...), $checks)) {
                $violations[] = 'aggregate job `' . $id . '` must need exactly the checks job (' . implode(', ', $checks) . ') and run with `always()`: a skipped or cancelled part would pass it otherwise';
            }

            $results = array_map(static fn (array $step): mixed => GithubWorkflowReader::at($step, 'env', 'RESULT'), GithubWorkflowReader::steps($job));

            if (! array_any($checks, static fn (int|string $check): bool => in_array('${{ needs.' . $check . '.result }}', $results, true))) {
                $violations[] = 'aggregate job `' . $id . '` does not read RESULT from `needs.<checks job>.result`';
            }
        }

        return $violations;
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    private function crons(array $workflow): array
    {
        $schedule = GithubWorkflowReader::triggers($workflow)['schedule'] ?? [];

        return array_values(array_filter(array_map(static fn (mixed $entry): mixed => is_array($entry) ? $entry['cron'] ?? null : null, is_array($schedule) ? $schedule : []), is_string(...)));
    }

    /**
     * @param array<mixed> $job
     * @return list<string>
     */
    private function checksJobRules(string $id, array $job, string $event): array
    {
        $violations = [];

        if (GithubWorkflowReader::condition($job) !== 'github.event_name ' . GithubWorkflowReader::EQUALS . " '" . $event . "'") {
            $violations[] = 'job `' . $id . '` has another condition than `github.event_name ' . GithubWorkflowReader::EQUALS . " '" . $event . "'`: a condition narrows the checks";
        }

        if (GithubWorkflowReader::needs($job) !== []) {
            $violations[] = 'job `' . $id . '` waits for ' . implode(', ', GithubWorkflowReader::needs($job)) . ': a checks job that another job can skip or fail first never reports its own result';
        }

        $matrix = GithubWorkflowReader::at($job, 'strategy', 'matrix');
        $matrix = is_array($matrix) ? $matrix : [];

        $declared = $matrix['part'] ?? null;
        $parts = GithubWorkflowReader::texts($declared);

        if (! is_array($declared) || array_diff($this->parts, $parts) !== [] || array_diff($parts, $this->parts) !== []) {
            $violations[] = 'the matrix of job `' . $id . '` is not exactly the parts of PARTS (' . implode(', ', $this->parts) . ')';
        }

        if (array_diff(array_keys($matrix), ['part']) !== []) {
            $violations[] = 'the matrix of job `' . $id . '` has the key `' . implode('`, `', array_diff(array_keys($matrix), ['part'])) . '` besides `part`: an exclude, an include or another axis drops or adds a part the guard cannot count';
        }

        foreach (GithubWorkflowReader::steps($job) as $step) {
            if (in_array(is_string($step['run'] ?? null) ? $step['run'] : '', [self::PART_LINE, self::MAIN_LINE], true)) {
                $violations = [...$violations, ...$this->checksStepRules($id, $step)];
            }
        }

        $env = is_array($job['env'] ?? null) ? $job['env'] : [];

        if ($event === 'pull_request' && ($env['CHECK_BASE'] ?? null) !== '${{ github.base_ref }}') {
            $violations[] = 'job `' . $id . '` does not set CHECK_BASE from `${{ github.base_ref }}`';
        }

        if (isset($env['CHECK_HEAD']) && GithubWorkflowReader::at(GithubWorkflowReader::checkout($job), 'with', 'ref') !== $env['CHECK_HEAD']) {
            $violations[] = 'job `' . $id . '` sets CHECK_HEAD but does not check out that revision: GitHub merges first, and the checks read HEAD';
        }

        return $violations;
    }

    /**
     * @param array<string, array<mixed>> $jobs
     * @return list<string>
     */
    private function testRules(array $jobs): array
    {
        $violations = [];

        foreach (['backend-tests', 'smoke', ...($this->frontend ? ['frontend-tests'] : [])] as $id) {
            if (! isset($jobs[$id])) {
                $violations[] = 'no job `' . $id . '`: it is a required status check, and a deleted job is a check nobody waits for';

                continue;
            }

            $events = GithubWorkflowReader::events($jobs[$id]['if'] ?? null);

            if (array_diff(['pull_request', 'push'], $events) !== []) {
                $violations[] = 'job `' . $id . '` does not run on every pull request and every push to a deploying branch: a skipped job reports success, a path filter may sit on a test run only';
            }
        }

        $backend = $jobs['backend-tests'] ?? [];

        if ($backend !== [] && ! in_array($this->backendTestCommand, GithubWorkflowReader::lines($backend), true)) {
            $violations[] = 'job `backend-tests` does not run exactly `' . $this->backendTestCommand . '`';
        }

        $holdsCoverage = static fn (string $line): bool => str_starts_with($line, GithubWorkflowReader::CHECKS . ' --coverage ');
        $pieces = preg_split('/\s*(?:&&|;|\n)\s*/', mb_trim($this->backendTestScript));
        $inScript = $pieces !== false && array_any($pieces, $holdsCoverage);

        if ($backend !== [] && ! array_any(GithubWorkflowReader::lines($backend), $holdsCoverage) && ! $inScript) {
            $violations[] = 'job `backend-tests` has no line `' . GithubWorkflowReader::CHECKS . ' --coverage <report>`' . ($this->backendTestScript === '' ? '' : ' and the script behind `' . $this->backendTestCommand . '` does not run it') . ': the coverage of the run is held at the committed floor';
        }

        if ($this->frontend && $this->smokeBrowser !== '' && isset($jobs['smoke']) && ! array_any(GithubWorkflowReader::lines($jobs['smoke']), fn (string $line): bool => str_contains($line, $this->smokeBrowser))) {
            $violations[] = 'job `smoke` does not run `' . $this->smokeBrowser . '`: the smoke run of a project with a frontend runs in a browser';
        }

        return [...$violations, ...$this->serviceRules($jobs)];
    }

    /**
     * @param array<string, array<mixed>> $jobs
     * @param list<string> $crons the cron expressions of the workflow
     * @return list<string>
     */
    private function scheduledRules(array $jobs, array $crons): array
    {
        $scheduled = array_filter($jobs, static fn (array $job): bool => in_array(GithubWorkflowReader::CHECKS . ' --scheduled', GithubWorkflowReader::lines($job), true));

        if ($scheduled === []) {
            return ['no job runs exactly `' . GithubWorkflowReader::CHECKS . ' --scheduled`'];
        }

        $violations = [];

        foreach ($scheduled as $id => $job) {
            if (GithubWorkflowReader::condition($job) !== 'github.event_name ' . GithubWorkflowReader::EQUALS . " 'schedule'") {
                $violations[] = 'job `' . $id . '` runs the scheduled checks on another condition than the schedule event';
            }

            if (! in_array(self::MUTATION_LINE, GithubWorkflowReader::lines($job), true)) {
                $violations[] = 'job `' . $id . '` does not run `' . self::MUTATION_LINE . '`: the whole-tree mutation run belongs to the scheduled job';
            }

            foreach (GithubWorkflowReader::steps($job) as $step) {
                $condition = isset($step['if']) ? mb_trim(GithubWorkflowReader::text($step['if'])) : null;

                if (($step['run'] ?? null) === self::MUTATION_LINE && $condition !== null && (preg_match('/^github\.event\.schedule ' . GithubWorkflowReader::EQUALS . ' \'([^\']+)\'$/', $condition, $match) !== 1 || ! in_array($match[1], $crons, true))) {
                    $violations[] = 'the mutation step of job `' . $id . '` has a condition that is not `github.event.schedule ' . GithubWorkflowReader::EQUALS . " '<one of the crons of the schedule trigger>'`: a weekly run is that condition on the cron of its own, and any other condition may never be true";
                }
            }
        }

        return $violations;
    }

    /**
     * @param array<string, array<mixed>> $jobs
     * @return list<string>
     */
    private function serviceRules(array $jobs): array
    {
        $violations = [];

        foreach ($this->databaseImage === '' ? [] : $jobs as $id => $job) {
            if (! in_array($id, ['backend-tests', 'smoke'], true) && ! in_array(GithubWorkflowReader::CHECKS . ' --scheduled', GithubWorkflowReader::lines($job), true)) {
                continue;
            }

            if (! is_array($job['services'] ?? null) || $job['services'] === []) {
                $violations[] = 'job `' . $id . '` has no database service: the tests and the smoke run use the production engine (`' . $this->databaseImage . '`), not what the image happens to hold';
            }
        }

        return $violations;
    }
}
