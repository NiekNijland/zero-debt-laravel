<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class AuxiliarySecretRule
{
    private const array SAFE_TRIGGERS = ['schedule', 'workflow_dispatch'];

    /**
     * @param list<string> $declared the secret names the owner declared for this file
     */
    public function __construct(private array $declared)
    {
    }

    /**
     * @param list<string> $triggers the event names under `on:` of the workflow
     * @param list<string> $secrets the names read through `secrets.<NAME>`, without the workflow token
     * @return list<string>
     */
    public function violations(array $triggers, array $secrets): array
    {
        $violations = [];

        foreach (array_diff($triggers, self::SAFE_TRIGGERS) as $trigger) {
            $violations[] = "a workflow that reads a secret is started by `{$trigger}`: only `schedule` and `workflow_dispatch` run the copy on the default branch";
        }

        foreach (array_diff($secrets, $this->declared) as $name) {
            $violations[] = "the workflow reads `secrets.{$name}`, which the owner did not declare for this file";
        }

        foreach (array_diff($this->declared, $secrets) as $name) {
            $violations[] = "the declared secret `{$name}` is not read by the workflow: remove it from the declaration";
        }

        return $violations;
    }
}
