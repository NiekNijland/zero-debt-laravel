<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class TaskFlagsGuard
{
    public const array RULES = ['checks-script'];

    /**
     * @param array<string, string> $taskFlags a task name and the flag its command carries, so the check stays on the whole tree and blocks
     */
    public function __construct(
        private array $taskFlags,
    ) {
    }

    /**
     * @return list<string>
     */
    public function inChecks(string $checksScript): array
    {
        $violations = [];

        foreach ($this->taskFlags as $task => $flag) {
            if (preg_match('/"' . preg_quote($task, '/') . '::([^"]*)"/', $checksScript, $match) === 1 && ! str_contains($match[1], $flag)) {
                $violations[] = '[checks-script] the task "' . $task . '" runs without `' . $flag . '`: it must check the whole tree and block';
            }
        }

        return $violations;
    }
}
