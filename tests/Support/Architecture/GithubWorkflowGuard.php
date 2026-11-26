<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Symfony\Component\Yaml\Yaml;

final readonly class GithubWorkflowGuard
{
    private const array EVENTS = ['pull_request', 'push', 'schedule'];

    /**
     * @param string $checksScript the project's checks script, whose PARTS table names the parts
     * @param string $kitWorkflow the file name of the kit's own workflow inside the workflow directory
     * @param list<string> $deployBranches the branches that deploy: `push.branches` of the kit's file covers each of them
     * @param string $databaseImage DATABASE_IMAGE of scripts/kit-config.sh, compared as written; empty where the project has no database
     * @param bool $frontend whether the project has a frontend (a `frontend-tests` job, a browser in the smoke run)
     * @param string $smokeBrowser the text the smoke command holds when it runs in a browser
     * @param list<string> $allowedTriggers owner exceptions: triggers of the kit's file that are no check (`workflow_run`, `workflow_dispatch`)
     * @param list<string> $writingLines run lines an owner exception allows to write
     * @param list<string> $hardenedWorkflows other workflow files (by name) whose jobs and hazards the owner has the guard read
     * @param list<string> $auxiliaryImages service images besides DATABASE_IMAGE that the jobs start (Redis), compared as written
     * @param string $hostVariable the job variable that holds the database host (a URI for MongoDB: `MONGODB_URI`)
     * @param int $maxTimeoutMinutes the longest `timeout-minutes` a job may ask for
     * @param list<string> $auxiliaryWorkflows other workflow files (by name) that run the checks script on purpose and are no kit file (a nightly canary, a sync run, a manual trigger): their jobs and hazards are read, but they need no pull_request or push trigger, no aggregate job and no matrix, and may have `workflow_dispatch`
     * @param string $backendTestScript the text of the script behind `$backendTestCommand` (the Composer script); give it when the coverage line `bash scripts/checks.sh --coverage <report>` ends that script and not the job
     */
    public function __construct(
        private string $workflowDirectory,
        private string $checksScript,
        private string $kitWorkflow,
        private array $deployBranches,
        private string $backendTestCommand,
        private string $databaseImage,
        private bool $frontend = true,
        private string $smokeBrowser = '',
        private array $allowedTriggers = [],
        private array $writingLines = [],
        private array $hardenedWorkflows = [],
        private int $maxTimeoutMinutes = 120,
        private array $auxiliaryImages = [],
        private string $hostVariable = 'DB_HOST',
        private string $backendTestScript = '',
        private array $auxiliaryWorkflows = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $yml = glob($this->workflowDirectory . '/*.yml');
        $yaml = glob($this->workflowDirectory . '/*.yaml');
        $files = [...($yml === false ? [] : $yml), ...($yaml === false ? [] : $yaml)];

        if ($files === []) {
            return ['no workflow under ' . $this->workflowDirectory . ': copy ci/github-actions.yml to ' . $this->workflowDirectory . '/' . $this->kitWorkflow];
        }

        if (! is_file($this->checksScript)) {
            return ['the checks script ' . $this->checksScript . ' does not exist: the parts of the checks step are read from it'];
        }

        return $this->inWorkflows(array_combine(array_map(basename(...), $files), array_map(static fn (string $file): string => (string) file_get_contents($file), $files)), (string) file_get_contents($this->checksScript));
    }

    /**
     * @param array<string, string> $yamlByFile
     * @param string $checksText the text of the checks script
     * @return list<string>
     */
    public function inWorkflows(array $yamlByFile, string $checksText): array
    {
        $violations = [];
        $kitFiles = 0;
        $testsPerEvent = array_fill_keys(self::EVENTS, 0);

        foreach ($yamlByFile as $name => $yaml) {
            $workflow = Yaml::parse($yaml);
            $workflow = is_array($workflow) ? $workflow : [];
            $isAuxiliary = in_array($name, $this->auxiliaryWorkflows, true) && $name !== $this->kitWorkflow;
            $isKit = ! $isAuxiliary && ($name === $this->kitWorkflow || GithubWorkflowReader::runsChecks($workflow));
            $kitFiles += $isKit ? 1 : 0;

            $violations = [...$violations, ...array_map(static fn (string $line): string => $name . ': ' . $line, $this->inWorkflow($workflow, $isKit, $isAuxiliary || in_array($name, $this->hardenedWorkflows, true), $checksText))];

            foreach (GithubWorkflowReader::jobs($workflow) as $job) {
                if (in_array($this->backendTestCommand, GithubWorkflowReader::lines($job), true)) {
                    foreach (GithubWorkflowReader::events($job['if'] ?? null) as $event) {
                        $testsPerEvent[$event]++;
                    }
                }
            }
        }

        if ($kitFiles === 0) {
            $violations[] = 'no workflow runs `bash scripts/checks.sh`: the checks job is missing';
        }

        foreach ($testsPerEvent as $event => $count) {
            if ($count > 1) {
                $violations[] = 'the backend tests run ' . $count . ' times on one ' . $event . ' event (two jobs or two workflows match it): a branch runs each suite once before its deploy';
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    private function inWorkflow(array $workflow, bool $isKit, bool $hardened, string $checksText): array
    {
        $jobRules = new GithubJobRules($this->databaseImage, $this->writingLines, $this->maxTimeoutMinutes, $this->auxiliaryImages, $this->hostVariable);

        if (! $isKit) {
            return $hardened ? [...$jobRules->inWorkflow($workflow), ...GithubWorkflowRules::hazards($workflow)] : [];
        }

        $checks = new GithubChecksRules(ChecksParts::of($checksText), $this->backendTestCommand, $this->databaseImage, $this->frontend, $this->smokeBrowser, $this->backendTestScript);

        return [
            ...$jobRules->inWorkflow($workflow),
            ...GithubWorkflowRules::hazards($workflow),
            ...new GithubWorkflowRules($this->deployBranches, $this->allowedTriggers)->inKitWorkflow($workflow),
            ...GithubRunnerRules::violations($workflow, $this->frontend),
            ...$checks->violations($workflow),
        ];
    }
}
