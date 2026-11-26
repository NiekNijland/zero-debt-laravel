<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class GithubWorkflowGuard
{
    public const array RULES = ['five-runs', 'checks-parts', 'pinning', 'time-limits', 'nothing-fails', 'database-and-drivers', 'lock-installs', 'scheduled-run', 'mutation', 'full-history', 'one-suite-per-trigger', 'read-only'];

    private const array EVENTS = ['pull_request', 'push', 'schedule'];

    private const string EQUALS = '=' . '=';

    private GithubJobRules $jobs;

    private GithubTriggerRules $triggerGuard;

    /**
     * @param list<string> $deployBranches the branches that deploy: `push.branches` of the kit's file covers each of them
     * @param list<string> $parts the parts of the checks step, as the checks script lists them
     * @param array<string, string> $productionDrivers the environment of the smoke job: a driver variable and the value of production
     * @param list<string> $allowedTriggers owner exceptions: triggers of a deploy workflow that are no check
     * @param list<string> $writingLines run lines an owner exception allows to write
     */
    public function __construct(
        private string $workflowDirectory,
        array $deployBranches,
        private string $backendTestCommand,
        private string $checksCommand,
        private string $mutationCommand,
        private string $smokeJob,
        private array $productionDrivers,
        string $databaseImage,
        private array $parts,
        array $allowedTriggers = [],
        array $writingLines = [],
    ) {
        $this->triggerGuard = new GithubTriggerRules($deployBranches, $allowedTriggers);
        $this->jobs = new GithubJobRules($databaseImage, new CiSafetyGuard('', [], [], [], '', '', $writingLines));
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $files = glob($this->workflowDirectory . '/*.{yml,yaml}', GLOB_BRACE);

        if ($files === false || $files === []) {
            return ['[five-runs] no workflow in ' . $this->workflowDirectory . ": the kit's jobs sit in a workflow file of their own"];
        }

        return $this->inWorkflows(array_combine(array_map(basename(...), $files), array_map(static fn (string $file): string => (string) file_get_contents($file), $files)));
    }

    /**
     * @param array<string, string> $yamlByFile
     * @return list<string>
     */
    public function inWorkflows(array $yamlByFile): array
    {
        $violations = [];
        $kitFiles = 0;
        $suites = array_fill_keys(self::EVENTS, 0);

        foreach ($yamlByFile as $name => $yaml) {
            $failure = YamlNode::failure($yaml);

            if ($failure !== null) {
                $violations[] = $name . ': [five-runs] the workflow is not valid YAML: ' . $failure;

                continue;
            }

            $workflow = YamlNode::parse($yaml);
            $isKit = $this->runsChecks($workflow);
            $kitFiles += $isKit ? 1 : 0;
            $violations = [...$violations, ...array_map(static fn (string $line): string => $name . ': ' . $line, $this->inWorkflow($workflow, $isKit))];

            foreach ($this->jobs($workflow) as $job) {
                if (in_array($this->backendTestCommand, self::lines($job), true)) {
                    foreach ($this->events($job['if'] ?? null) as $event) {
                        $suites[$event]++;
                    }
                }
            }
        }

        if ($kitFiles === 0) {
            $violations[] = '[five-runs] no workflow runs `' . $this->checksCommand . '`: the checks job with the matrix of parts is missing';
        }

        foreach ($suites as $event => $count) {
            if ($count > 1) {
                $violations[] = '[one-suite-per-trigger] the backend tests run ' . $count . ' times on one ' . $event . ' event (two jobs or two workflows match it): a branch runs each suite once before its deploy';
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * @param array<string, mixed> $job
     * @return list<string>
     */
    private static function lines(array $job): array
    {
        $lines = [];

        foreach (YamlNode::items($job['steps'] ?? null) as $step) {
            array_push($lines, ...GithubJobRules::lines(YamlNode::text(YamlNode::at($step, 'run'))));
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $workflow
     * @return array<string, array<string, mixed>>
     */
    private function jobs(array $workflow): array
    {
        return array_map(YamlNode::map(...), array_filter(YamlNode::map($workflow['jobs'] ?? null), is_array(...)));
    }

    /**
     * @param array<string, mixed> $job
     */
    private function checkoutRef(array $job): mixed
    {
        foreach (YamlNode::items($job['steps'] ?? null) as $step) {
            if (str_starts_with(YamlNode::text(YamlNode::at($step, 'uses')), 'actions/checkout@')) {
                return YamlNode::at($step, 'with', 'ref');
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function events(mixed $condition): array
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
     * @param array<string, mixed> $workflow
     * @return list<string>
     */
    private function inWorkflow(array $workflow, bool $isKit): array
    {
        $violations = [];

        foreach ($this->jobs($workflow) as $id => $job) {
            $violations = [...$violations, ...$this->jobs->violations($id, $job)];
        }

        if (array_key_exists('pull_request_target', $this->triggerGuard->triggers($workflow))) {
            $violations[] = "[read-only] pull_request_target is used: it runs with secrets, and the pull request's own scripts would run in it";
        }

        return $isKit ? [...$violations, ...$this->triggerGuard->violations($workflow), ...$this->runRules($workflow)] : $violations;
    }

    /**
     * @param array<string, mixed> $workflow
     * @return list<string>
     */
    private function runRules(array $workflow): array
    {
        $jobs = $this->jobs($workflow);
        $violations = [];

        foreach ([$this->checksCommand . ' --part ${{ matrix.part }}' => 'pull_request', $this->checksCommand . ' --main --part ${{ matrix.part }}' => 'push'] as $line => $event) {
            $carrying = array_filter($jobs, static fn (array $job): bool => in_array($line, self::lines($job), true));

            if ($carrying === []) {
                $violations[] = '[checks-parts] no job runs exactly `' . $line . '`: the ' . ($event === 'push' ? 'checks of a deploying branch' : 'checks of a pull request') . ' are missing';
            }

            foreach ($carrying as $id => $job) {
                $violations = [...$violations, ...$this->checksJobRules($id, $job, $event)];
            }
        }

        return [...$violations, ...$this->scheduledRules($jobs), ...$this->testRules($jobs), ...$this->aggregateRules($jobs)];
    }

    /**
     * @param array<string, array<string, mixed>> $jobs
     * @return list<string>
     */
    private function scheduledRules(array $jobs): array
    {
        $scheduled = array_filter($jobs, fn (array $job): bool => in_array($this->checksCommand . ' --scheduled', self::lines($job), true));
        $violations = $scheduled === [] ? ['[scheduled-run] no job runs exactly `' . $this->checksCommand . ' --scheduled`'] : [];

        foreach ($scheduled as $id => $job) {
            if (mb_trim(YamlNode::text($job['if'] ?? '')) !== 'github.event_name ' . self::EQUALS . " 'schedule'") {
                $violations[] = '[scheduled-run] job `' . $id . '` runs the scheduled checks on another condition than the schedule event';
            }

            if (! in_array($this->mutationCommand, self::lines($job), true)) {
                $violations[] = '[scheduled-run] job `' . $id . '` does not run the whole-tree mutation run `' . $this->mutationCommand . '`';
            }
        }

        return $violations;
    }

    /**
     * @param array<string, array<string, mixed>> $jobs
     * @return list<string>
     */
    private function testRules(array $jobs): array
    {
        $violations = [];
        $tests = array_filter($jobs, fn (array $job): bool => in_array($this->backendTestCommand, self::lines($job), true));

        foreach ($tests as $id => $job) {
            if (! in_array($this->mutationCommand . ' --changed', self::lines($job), true)) {
                $violations[] = '[mutation] job `' . $id . '` does not run the mutation tests on the changed files of a pull request (`' . $this->mutationCommand . ' --changed`)';
            }
        }

        $smoke = YamlNode::map($jobs[$this->smokeJob] ?? null);

        if ($smoke === []) {
            return [...$violations, '[database-and-drivers] no smoke job `' . $this->smokeJob . '`: a deploy needs a stack booted with the production drivers'];
        }

        foreach ($this->productionDrivers as $variable => $value) {
            if (YamlNode::text(YamlNode::at($smoke, 'env', $variable)) !== $value) {
                $violations[] = '[database-and-drivers] the smoke job does not set ' . $variable . ' to `' . $value . '`: it must boot the production drivers';
            }
        }

        return $violations;
    }

    /**
     * @param array<string, array<string, mixed>> $jobs
     * @return list<string>
     */
    private function aggregateRules(array $jobs): array
    {
        return array_any($jobs, static fn (array $job): bool => isset($job['needs']) && str_contains(YamlNode::text($job['if'] ?? ''), 'always()') && in_array('test "$RESULT" = success', self::lines($job), true))
            ? []
            : ['[checks-parts] no aggregate job: a job with `needs`, `if: always()` and the step `test "$RESULT" = success` is the one required status check that follows the parts'];
    }

    /**
     * @param array<string, mixed> $job
     * @return list<string>
     */
    private function checksJobRules(string $id, array $job, string $event): array
    {
        $violations = [];

        if (mb_trim(YamlNode::text($job['if'] ?? '')) !== 'github.event_name ' . self::EQUALS . " '" . $event . "'") {
            $violations[] = '[checks-parts] job `' . $id . '` has another condition than `github.event_name ' . self::EQUALS . " '" . $event . "'`: a condition narrows the checks";
        }

        $matrix = YamlNode::texts(YamlNode::at($job, 'strategy', 'matrix', 'part'));

        if (array_diff($this->parts, $matrix) !== [] || array_diff($matrix, $this->parts) !== []) {
            $violations[] = '[checks-parts] the matrix of job `' . $id . '` is not exactly the parts of the checks script (' . implode(', ', $this->parts) . ')';
        }

        foreach (YamlNode::items($job['steps'] ?? null) as $node) {
            $step = YamlNode::map($node);

            if (str_starts_with(YamlNode::text($step['run'] ?? ''), $this->checksCommand) && isset($step['if'])) {
                $violations[] = '[checks-parts] the checks step of job `' . $id . '` has a condition of its own';
            }
        }

        $env = YamlNode::map($job['env'] ?? null);

        if ($event === 'pull_request' && ($env['CHECK_BASE'] ?? null) !== '${{ github.base_ref }}') {
            $violations[] = '[checks-parts] job `' . $id . '` does not set CHECK_BASE from `${{ github.base_ref }}`';
        }

        if (isset($env['CHECK_HEAD']) && $this->checkoutRef($job) !== $env['CHECK_HEAD']) {
            $violations[] = '[checks-parts] job `' . $id . '` sets CHECK_HEAD but does not check out that revision: GitHub merges first, and the checks read HEAD';
        }

        return $violations;
    }

    /**
     * @param array<string, mixed> $workflow
     */
    private function runsChecks(array $workflow): bool
    {
        return array_any($this->jobs($workflow), fn (array $job): bool => array_any(self::lines($job), fn (string $line): bool => in_array($line, [$this->checksCommand . ' --part ${{ matrix.part }}', $this->checksCommand . ' --main --part ${{ matrix.part }}'], true)));
    }
}
