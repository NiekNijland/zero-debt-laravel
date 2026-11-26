<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class GithubTriggerRules
{
    private const array EVENTS = ['pull_request', 'push', 'schedule'];

    /**
     * @param list<string> $deployBranches the branches that deploy: `push.branches` of the kit's file covers each of them
     * @param list<string> $allowedTriggers owner exceptions: triggers of a deploy workflow that are no check
     */
    public function __construct(
        private array $deployBranches,
        private array $allowedTriggers,
    ) {
    }

    /**
     * @param array<string, mixed> $workflow
     * @return list<string>
     */
    public function violations(array $workflow): array
    {
        return [...$this->triggerRules($workflow), ...$this->settingRules($workflow)];
    }

    /**
     * @param array<string, mixed> $workflow
     * @return array<string, mixed>
     */
    public function triggers(array $workflow): array
    {
        $on = YamlNode::at($workflow, 'on') ?? YamlNode::at($workflow, '1') ?? [];

        if (is_string($on)) {
            return [$on => null];
        }

        return is_array($on) && array_is_list($on) ? array_fill_keys(YamlNode::texts($on), null) : YamlNode::map($on);
    }

    private static function writes(mixed $permissions): bool
    {
        return is_string($permissions)
            ? str_starts_with($permissions, 'write')
            : in_array('write', array_map(YamlNode::text(...), array_values(YamlNode::map($permissions))), true);
    }

    /**
     * @param array<string, mixed> $workflow
     * @return list<string>
     */
    private function triggerRules(array $workflow): array
    {
        $on = $this->triggers($workflow);
        $violations = [];

        foreach (array_keys($on) as $trigger) {
            if (! in_array($trigger, [...self::EVENTS, ...$this->allowedTriggers], true)) {
                $violations[] = '[no-manual-trigger] the trigger `' . $trigger . '` is not one of pull_request, push, schedule: a check nobody has to run is a report (an owner exception is the allowedTriggers argument)';
            }
        }

        foreach (self::EVENTS as $event) {
            if (! array_key_exists($event, $on)) {
                $violations[] = '[five-runs] the workflow has no `' . $event . '` trigger';
            }
        }

        $pullRequest = YamlNode::map($on['pull_request'] ?? null);

        foreach (['paths', 'paths-ignore', 'branches', 'branches-ignore'] as $filter) {
            if (isset($pullRequest[$filter])) {
                $violations[] = '[five-runs] pull_request has `' . $filter . '`: the checks of a pull request have no filter (a pull request that targets a parent branch of a stack matches no `branches` list either)';
            }
        }

        foreach (['opened', 'synchronize', 'reopened', 'edited'] as $type) {
            if (! in_array($type, YamlNode::texts($pullRequest['types'] ?? ['opened', 'synchronize', 'reopened']), true)) {
                $violations[] = '[five-runs] pull_request.types lacks `' . $type . '`: `edited` re-runs the checks after a retarget, the others are the default';
            }
        }

        return [...$violations, ...$this->pushAndScheduleRules($on)];
    }

    /**
     * @param array<string, mixed> $on
     * @return list<string>
     */
    private function pushAndScheduleRules(array $on): array
    {
        $push = YamlNode::map($on['push'] ?? null);
        $violations = [];

        foreach ($this->deployBranches as $branch) {
            if (! in_array($branch, YamlNode::texts($push['branches'] ?? null), true)) {
                $violations[] = '[five-runs] push.branches does not list the deploying branch ' . $branch;
            }
        }

        if (isset($push['paths']) || isset($push['paths-ignore'])) {
            $violations[] = '[five-runs] push has a path filter: the checks of a deploying branch have none';
        }

        if (! array_any(YamlNode::items($on['schedule'] ?? null), static fn (mixed $entry): bool => is_string(YamlNode::at($entry, 'cron')))) {
            $violations[] = '[five-runs] the schedule trigger has no cron';
        }

        return $violations;
    }

    /**
     * @param array<string, mixed> $workflow
     * @return list<string>
     */
    private function settingRules(array $workflow): array
    {
        $violations = [];
        $concurrency = YamlNode::map($workflow['concurrency'] ?? null);

        if (! isset($concurrency['group'])) {
            $violations[] = '[read-only] the workflow has no concurrency group: every push to a pull request leaves its older run going';
        } elseif (($concurrency['cancel-in-progress'] ?? false) === true || (is_string($concurrency['cancel-in-progress'] ?? null) && ! str_contains($concurrency['cancel-in-progress'], 'pull_request'))) {
            $violations[] = '[read-only] concurrency cancels runs of every event: only a pull request may cancel its older run, a push to a deploying branch is never cancelled';
        }

        if (! isset($workflow['permissions'])) {
            $violations[] = '[read-only] the workflow has no top-level `permissions`: set contents: read';
        }

        if (self::writes($workflow['permissions'] ?? null) || array_any(YamlNode::map($workflow['jobs'] ?? null), static fn (mixed $job): bool => self::writes(YamlNode::at($job, 'permissions')))) {
            $violations[] = '[read-only] a permission is `write`: a check job reads (contents: read, and actions: read where the dashboard asks the run history)';
        }

        return $violations;
    }
}
