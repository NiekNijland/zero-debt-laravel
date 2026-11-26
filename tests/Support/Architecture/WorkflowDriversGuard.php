<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\Narrow;

final readonly class WorkflowDriversGuard
{
    public const array RULES = ['drivers', 'mutation'];

    /**
     * @param array<string, string> $drivers the variables of the smoke job and the value each has: the production drivers of the stack under test
     */
    public function __construct(
        private string $workflowFile,
        private string $smokeJob,
        private array $drivers,
        private string $testJob,
        private string $changedMutationCommand,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        throw_unless(is_file($this->workflowFile), RuntimeException::class, 'the workflow file ' . $this->workflowFile . ' does not exist');

        return $this->inWorkflow((string) file_get_contents($this->workflowFile));
    }

    /**
     * @return list<string>
     */
    public function inWorkflow(string $yaml): array
    {
        $workflow = Narrow::map(Yaml::parse($yaml));
        $jobs = Narrow::map($workflow['jobs'] ?? null);
        $violations = [];

        if (! isset($jobs[$this->smokeJob])) {
            return ['[drivers] no smoke job `' . $this->smokeJob . '`'];
        }

        $env = Narrow::map(Narrow::map($jobs[$this->smokeJob])['env'] ?? null);

        foreach ($this->drivers as $variable => $value) {
            if (! isset($env[$variable]) || Narrow::string($env[$variable]) !== $value) {
                $violations[] = '[drivers] the smoke job does not set ' . $variable . ' to `' . $value . '`: it runs the drivers of production';
            }
        }

        $steps = Narrow::map($jobs[$this->testJob] ?? null)['steps'] ?? null;
        $commands = array_map(static fn (mixed $step): string => Narrow::string(Narrow::map($step)['run'] ?? ''), is_array($steps) ? $steps : []);

        if (! in_array($this->changedMutationCommand, $commands, true)) {
            $violations[] = '[mutation] job `' . $this->testJob . '` does not run `' . $this->changedMutationCommand . '`: a pull request runs the mutation tests on the files it changes';
        }

        return $violations;
    }
}
