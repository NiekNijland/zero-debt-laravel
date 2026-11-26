<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class GithubJobRules
{
    private const array CONTAINER_OPTIONS = ['--user', '--cpus', '--memory', '--shm-size'];

    /**
     * @param string $databaseImage DATABASE_IMAGE of the kit config, compared as written; empty where the project has no database
     * @param list<string> $writingLines run lines an owner exception allows to write
     * @param int $maxTimeoutMinutes the longest `timeout-minutes` a job may ask for
     * @param list<string> $auxiliaryImages service images besides DATABASE_IMAGE that a job may start (Redis for the cache and the queue), compared as written
     * @param string $hostVariable the job variable that tells the application where its database is (`DB_HOST`; `MONGODB_URI` for MongoDB)
     */
    public function __construct(
        private string $databaseImage,
        private array $writingLines,
        private int $maxTimeoutMinutes,
        private array $auxiliaryImages = [],
        private string $hostVariable = 'DB_HOST',
    ) {
    }

    /**
     * @param array<mixed> $workflow
     * @return list<string>
     */
    public function inWorkflow(array $workflow): array
    {
        $violations = [];
        $jobs = GithubWorkflowReader::jobs($workflow);

        foreach ($jobs as $id => $job) {
            $violations = [...$violations, ...$this->inJob($id, $job, is_array($workflow['env'] ?? null) ? $workflow['env'] : [])];

            $name = $job['name'] ?? null;

            if (is_string($name) && $name !== $id && (isset($jobs[$name]) || count(array_filter($jobs, static fn (array $other): bool => ($other['name'] ?? null) === $name)) > 1)) {
                $violations[] = 'job `' . $id . '` is named `' . $name . '`, which is the name of another job: two jobs reporting one check name make the required check ambiguous';
            }
        }

        return $violations;
    }

    /**
     * @param array<mixed> $env
     */
    private static function fetchesOnlyLocalUrls(string $line, array $env): bool
    {
        preg_match_all('~"[^"]*"|\'[^\']*\'|\S+~', $line, $matches);

        $urls = [];

        foreach ($matches[0] as $word) {
            $word = mb_trim($word, '"\'');

            if (preg_match('~^\$\{?([A-Za-z_]\w*)\}?(.*)$~', $word, $variable) === 1) {
                $value = $env[$variable[1]] ?? null;
                $word = is_string($value) ? $value . $variable[2] : '';
            }

            if (preg_match('~^https?://~', $word) === 1) {
                $urls[] = $word;
            }
        }

        return $urls !== [] && ! array_any($urls, static fn (string $url): bool => preg_match('~^https?://(127\.0\.0\.1|localhost|0\.0\.0\.0|\[::1\])([:/]|$)~', $url) !== 1);
    }

    /**
     * @param list<string> $words the conditions that count, lower case
     */
    private function switchedOff(mixed $condition, array $words): bool
    {
        if ($condition === false) {
            return in_array('false', $words, true);
        }

        if (! is_string($condition)) {
            return false;
        }

        $text = mb_strtolower(mb_trim($condition));
        $text = preg_replace('/^\$\{\{\s*(.*?)\s*\}\}$/', '$1', $text) ?? $text;

        return in_array($text, $words, true);
    }

    /**
     * @param array<mixed> $job
     * @param array<mixed> $workflowEnv
     * @return list<string>
     */
    private function downloadRules(string $where, array $job, array $workflowEnv): array
    {
        $lines = GithubWorkflowReader::lines($job);
        $env = [...$workflowEnv, ...(is_array($job['env'] ?? null) ? $job['env'] : [])];
        $calls = array_filter($lines, static fn (string $line): bool => preg_match('/\b(curl|wget)\b/', $line) === 1);
        $downloads = array_filter($calls, static fn (string $line): bool => ! self::fetchesOnlyLocalUrls($line, $env));
        $violations = [];

        if ($downloads !== [] && ! array_any($lines, static fn (string $line): bool => preg_match('/\bsha256sum\s+(-c|--check)\b|\bshasum\s+(-a\s*256\s+)?-c\b/', $line) === 1)) {
            $violations[] = $where . ' downloads a file with curl or wget and checks no checksum (`sha256sum -c`): a downloaded binary is pinned by its version and its checksum';
        }

        foreach ($calls as $line) {
            if (preg_match('/\|\s*(sudo\s+)?(ba)?sh\b/', $line) === 1) {
                $violations[] = $where . ' pipes a download into a shell: `' . $line . '`';
            }
        }

        return $violations;
    }

    private function pinned(string $uses): bool
    {
        return preg_match('/@([0-9a-f]{40}|sha256:[0-9a-f]{64})$/', $uses) === 1;
    }

    /**
     * @param array<mixed> $step
     * @return list<string>
     */
    private function checkoutRules(string $where, array $step): array
    {
        $violations = [];

        if (GithubWorkflowReader::at($step, 'with', 'fetch-depth') !== 0) {
            $violations[] = $where . ' checks out with a shallow clone: set `fetch-depth: 0` (a history-reading task stops working after a few dozen commits)';
        }

        if (GithubWorkflowReader::at($step, 'with', 'persist-credentials') === false) {
            $violations[] = $where . ' checks out with `persist-credentials: false`: scripts/checks.sh fetches the base with those credentials';
        }

        return $violations;
    }

    /**
     * @param array<mixed> $job
     * @param array<mixed> $workflowEnv
     * @return list<string>
     */
    private function inJob(string $id, array $job, array $workflowEnv): array
    {
        $where = 'job `' . $id . '`';
        $violations = [];
        $timeout = $job['timeout-minutes'] ?? null;

        if (! is_int($timeout)) {
            $violations[] = $where . " has no integer timeout-minutes (GitHub's default is 360 minutes)";
        } elseif ($timeout < 1 || $timeout > $this->maxTimeoutMinutes) {
            $violations[] = $where . ' has timeout-minutes ' . $timeout . ', above the ' . $this->maxTimeoutMinutes . ' the guard allows: a hung job holds a runner for that long';
        }

        if (isset($job['runs-on']) && preg_match('/latest/', implode(' ', GithubWorkflowReader::strings($job['runs-on']))) === 1) {
            $violations[] = $where . ' runs on a moving label (`*-latest`)';
        }

        if (is_string($job['uses'] ?? null) && ! $this->pinned($job['uses'])) {
            $violations[] = $where . ' calls the workflow `' . $job['uses'] . '`, which is not pinned to a full commit sha';
        }

        return [...$violations, ...$this->imageRules($where, $job), ...$this->stepRules($where, $job), ...$this->downloadRules($where, $job, $workflowEnv)];
    }

    /**
     * @param array<mixed> $job
     * @return list<string>
     */
    private function imageRules(string $where, array $job): array
    {
        $violations = [];
        $container = $job['container'] ?? null;
        $image = is_array($container) ? $container['image'] ?? null : $container;

        if (is_string($image) && ! str_contains($image, '@sha256:')) {
            $violations[] = $where . ' runs in a container that is not pinned by digest (`@sha256:`)';
        }

        $options = is_array($container) ? mb_trim(GithubWorkflowReader::text($container['options'] ?? '')) : '';

        $pieces = $options === '' ? [] : preg_split('/\s+(?=-)/', $options);

        foreach (is_array($pieces) ? $pieces : [] as $option) {
            if (! array_any(self::CONTAINER_OPTIONS, static fn (string $allowed): bool => str_starts_with($option, $allowed))) {
                $violations[] = $where . ' passes `' . $option . '` to its container: only ' . implode(', ', self::CONTAINER_OPTIONS) . ' are accepted (a privileged or host-networked container weakens the isolation of the checks)';
            }
        }

        foreach (is_array($job['services'] ?? null) ? $job['services'] : [] as $name => $service) {
            $violations = [...$violations, ...$this->serviceRules($where . ', service `' . $name . '`', $job, (string) $name, is_array($service) ? $service : [])];
        }

        return $violations;
    }

    /**
     * @param array<mixed> $job
     * @param array<mixed> $service
     * @return list<string>
     */
    private function serviceRules(string $label, array $job, string $name, array $service): array
    {
        $violations = [];
        $image = GithubWorkflowReader::text($service['image'] ?? '');
        $isDatabase = $image === $this->databaseImage;
        $known = $isDatabase || in_array($image, $this->auxiliaryImages, true);
        $engine = DatabaseEngine::of($known ? $image : $this->databaseImage);
        $env = is_array($service['env'] ?? null) ? $service['env'] : [];
        $options = GithubWorkflowReader::text($service['options'] ?? '');
        $onRunner = ! GithubWorkflowReader::truthy($job['container'] ?? null);

        if (! $known) {
            $violations[] = $label . ' image is not DATABASE_IMAGE of kit-config.sh (`' . $this->databaseImage . '`)' . ($this->auxiliaryImages === [] ? '' : ' or one of the auxiliary service images (`' . implode('`, `', $this->auxiliaryImages) . '`)') . ': the tests run on the production engine and major version';
        }

        if (($engine === '' || DatabaseEngine::needsPassword($engine)) && ($engine === '' ? $env === [] : ! array_any(DatabaseEngine::passwordKeys($engine), static fn (string $key): bool => isset($env[$key])))) {
            $violations[] = $label . ' has no password variable of its engine (' . ($engine === '' ? 'any' : $engine . ': ' . implode(', ', DatabaseEngine::passwordKeys($engine))) . '): the official image exits at start, and the job fails at "Initialize containers"';
        }

        if (! str_contains($options, '--health-cmd') || ($engine !== '' && ! array_any(DatabaseEngine::healthTools($engine), static fn (string $tool): bool => str_contains($options, $tool)))) {
            $violations[] = $label . ' has no health check of its engine (' . ($engine === '' ? '--health-cmd' : implode(' or ', DatabaseEngine::healthTools($engine))) . '): the job must wait for the database, and a check from another engine never passes';
        }

        if ($onRunner && ! GithubWorkflowReader::truthy($service['ports'] ?? null)) {
            $violations[] = $label . ' publishes no port and the job runs on the runner: it cannot reach the service';
        }

        if ($isDatabase) {
            $value = GithubWorkflowReader::text(GithubWorkflowReader::at($job, 'env', $this->hostVariable));
            $host = $onRunner ? '127.0.0.1' : $name;
            $fits = preg_match('#(^|[/@])' . preg_quote($host, '#') . '([:/]|$)#', $value) === 1;

            if (! $fits) {
                $violations[] = $label . ' is not the ' . $this->hostVariable . ' of its job: ' . ($onRunner ? 'a runner job reaches a service at 127.0.0.1 on its published port, never by the service name' : 'a container job reaches a service by its name');
            }
        }

        return $violations;
    }

    /**
     * @param array<mixed> $job
     * @return list<string>
     */
    private function stepRules(string $where, array $job): array
    {
        $violations = [];

        if (GithubWorkflowReader::truthy($job['continue-on-error'] ?? null) || $this->switchedOff($job['if'] ?? null, ['false'])) {
            $violations[] = $where . ' may fail or is switched off (`continue-on-error` or `if: false`): nothing is allowed to fail';
        }

        foreach (GithubWorkflowReader::steps($job) as $step) {
            $uses = is_string($step['uses'] ?? null) ? $step['uses'] : '';

            if ($uses !== '' && ! str_starts_with($uses, './') && ! $this->pinned($uses)) {
                $violations[] = $where . ' uses `' . $uses . '`, which is not pinned to a full commit sha';
            }

            if (str_starts_with($uses, 'actions/checkout@')) {
                $violations = [...$violations, ...$this->checkoutRules($where, $step)];
            }

            if (GithubWorkflowReader::truthy($step['continue-on-error'] ?? null) || $this->switchedOff($step['if'] ?? null, ['false', 'always()'])) {
                $violations[] = $where . ', a step may fail or is forced on (`continue-on-error`, `if: false`, `if: always()`)';
            }
        }

        return [...$violations, ...new CiSafetyGuard('', $this->writingLines)->inCommands(GithubWorkflowReader::lines($job))];
    }
}
