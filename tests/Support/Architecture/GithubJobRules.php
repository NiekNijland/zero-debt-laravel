<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class GithubJobRules
{
    /**
     * @param string $databaseImage DATABASE_IMAGE of the shared config, compared as written (the one image that needs no digest)
     */
    public function __construct(
        private string $databaseImage,
        private CiSafetyGuard $commands,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function lines(string $run): array
    {
        return array_values(array_filter(array_map(trim(...), explode("\n", $run)), static fn (string $line): bool => $line !== ''));
    }

    /**
     * @param array<string, mixed> $job
     * @return list<string>
     */
    public function violations(string $id, array $job): array
    {
        $where = 'job `' . $id . '`';

        return [
            ...$this->limits($where, $job),
            ...$this->images($where, $job),
            ...$this->services($where, $job),
            ...$this->steps($where, $job),
        ];
    }

    /**
     * @param array<string, mixed> $step
     * @return list<string>
     */
    private function checkout(string $where, array $step): array
    {
        $violations = [];

        if (YamlNode::at($step, 'with', 'fetch-depth') !== 0) {
            $violations[] = $this->fail('full-history', $where . ' checks out with a shallow clone: set `fetch-depth: 0` (a history-reading task stops working after a few dozen commits)');
        }

        if (YamlNode::at($step, 'with', 'persist-credentials') === false) {
            $violations[] = $this->fail('full-history', $where . ' checks out with `persist-credentials: false`: the checks script fetches the base with those credentials');
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function runViolations(string $where, string $run): array
    {
        $violations = [];

        if (preg_match('/\b(curl|wget)\b[^\n]*(\.tgz|\.tar\.gz|\.zip|\.phar|\.deb|\.sh\b|\|\s*(ba)?sh\b)/', $run) === 1 && preg_match('/\b(sha256sum|shasum)\b[^\n]*-c\b/', $run) !== 1) {
            $violations[] = $this->fail('pinning', $where . ' downloads a tool and does not check it against a checksum (`sha256sum -c`)');
        }

        if (preg_match('/\bcomposer\s+(update|require)\b|\bnpm\s+(install|i|update)\b|\byarn\s+(add|upgrade)\b|\bcomposer\s+install\b[^\n]*--no-lock/', $run) === 1) {
            $violations[] = $this->fail('lock-installs', $where . ' installs or updates a dependency outside the lock file: install with `composer install`');
        }

        return $violations;
    }

    /**
     * @param array<string, mixed> $step
     */
    private function label(array $step): string
    {
        return YamlNode::text($step['name'] ?? $step['uses'] ?? $step['run'] ?? '');
    }

    private function fail(string $rule, string $message): string
    {
        return '[' . $rule . '] ' . $message;
    }

    /**
     * @param array<string, mixed> $job
     * @return list<string>
     */
    private function limits(string $where, array $job): array
    {
        $violations = [];

        if (! is_int($job['timeout-minutes'] ?? null)) {
            $violations[] = $this->fail('time-limits', $where . ' has no integer timeout-minutes (the default of the host is 360 minutes)');
        }

        $runner = $job['runs-on'] ?? '';

        if (str_contains(implode(' ', is_array($runner) ? YamlNode::texts($runner) : [YamlNode::text($runner)]), 'latest')) {
            $violations[] = $this->fail('pinning', $where . ' runs on a moving label (`*-latest`)');
        }

        if (($job['continue-on-error'] ?? false) === true || ($job['if'] ?? null) === false || mb_trim(YamlNode::text($job['if'] ?? '')) === 'false') {
            $violations[] = $this->fail('nothing-fails', $where . ' may fail or is switched off (`continue-on-error` or `if: false`): nothing is allowed to fail');
        }

        return $violations;
    }

    /**
     * @param array<string, mixed> $job
     * @return list<string>
     */
    private function images(string $where, array $job): array
    {
        $container = $job['container'] ?? null;
        $image = is_array($container) ? YamlNode::text(YamlNode::at($container, 'image')) : YamlNode::text($container);

        return $image !== '' && ! str_contains($image, '@sha256:')
            ? [$this->fail('pinning', $where . ' runs in a container that is not pinned by digest (`@sha256:`)')]
            : [];
    }

    /**
     * @param array<string, mixed> $job
     * @return list<string>
     */
    private function services(string $where, array $job): array
    {
        $violations = [];
        $inContainer = is_array($job['container'] ?? null) || is_string($job['container'] ?? null);

        foreach (YamlNode::map($job['services'] ?? null) as $name => $node) {
            $service = YamlNode::map($node);
            $label = $where . ', service `' . $name . '`';

            if (YamlNode::text($service['image'] ?? '') !== $this->databaseImage) {
                $violations[] = $this->fail('database-and-drivers', $label . ' image is not the database image of the shared config (`' . $this->databaseImage . '`): the tests run on the production engine and major version');
            }

            if (! isset($service['env']) || ! str_contains(YamlNode::text($service['options'] ?? ''), '--health-cmd')) {
                $violations[] = $this->fail('database-and-drivers', $label . ' has no env or no health check: the official image exits without a password, and the job must wait for the database');
            }

            if (! $inContainer && ! isset($service['ports'])) {
                $violations[] = $this->fail('database-and-drivers', $label . ' publishes no port and the job runs on the runner: it cannot reach the service');
            }
        }

        return $violations;
    }

    /**
     * @param array<string, mixed> $job
     * @return list<string>
     */
    private function steps(string $where, array $job): array
    {
        $violations = [];

        foreach (YamlNode::items($job['steps'] ?? null) as $node) {
            $step = YamlNode::map($node);
            $uses = YamlNode::text($step['uses'] ?? '');
            $run = YamlNode::text($step['run'] ?? '');

            if (! is_int($step['timeout-minutes'] ?? null)) {
                $violations[] = $this->fail('time-limits', $where . ', step `' . $this->label($step) . '` has no integer timeout-minutes');
            }

            if ($uses !== '' && ! str_starts_with($uses, '.') && preg_match('/@([0-9a-f]{40}|sha256:[0-9a-f]{64})$/', $uses) !== 1) {
                $violations[] = $this->fail('pinning', $where . ' uses `' . $uses . '`, which is not pinned to a full commit sha');
            }

            if (str_starts_with($uses, 'actions/checkout@')) {
                $violations = [...$violations, ...$this->checkout($where, $step)];
            }

            if (($step['continue-on-error'] ?? false) === true || ($step['if'] ?? null) === false || in_array(mb_trim(YamlNode::text($step['if'] ?? '')), ['false', 'always()'], true)) {
                $violations[] = $this->fail('nothing-fails', $where . ', a step may fail or is forced on (`continue-on-error`, `if: false`, `if: always()`)');
            }

            $violations = [...$violations, ...$this->runViolations($where, $run), ...$this->commands->inCommands(self::lines($run))];
        }

        return $violations;
    }
}
