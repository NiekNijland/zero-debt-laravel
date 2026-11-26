<?php

declare(strict_types=1);

use Tests\Support\Architecture\WorkflowDriversGuard;
use Tests\Support\Ci\CiExamples;
use Tests\Support\Ci\CiHostVariants;
use Tests\Support\Ci\CiRunnerExamples;
use Tests\Support\Narrow;

uses()->group('arch');

$drivers = ['CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database', 'DB_CONNECTION' => 'pgsql'];
$guard = static fn (): WorkflowDriversGuard => new WorkflowDriversGuard('', 'smoke', ['CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database', 'DB_CONNECTION' => 'pgsql'], 'backend-tests', 'bash scripts/mutation.sh --changed');
$workflow = static fn (): string => CiExamples::change(
    CiExamples::changeAfter(CiRunnerExamples::workflow(), "\n  smoke:\n", '      DB_HOST: 127.0.0.1', "      DB_HOST: 127.0.0.1\n      CACHE_STORE: database\n      QUEUE_CONNECTION: database\n      DB_CONNECTION: pgsql"),
    "      - run: bash scripts/checks.sh --coverage coverage/clover.xml\n",
    "      - run: bash scripts/checks.sh --coverage coverage/clover.xml\n      - run: bash scripts/mutation.sh --changed\n",
);

dataset('workflows whose smoke run or mutation run drifts', [
    'a smoke job on another queue driver' => [fn (): string => CiExamples::change($workflow(), 'QUEUE_CONNECTION: database', 'QUEUE_CONNECTION: sync'), '[drivers] the smoke job does not set QUEUE_CONNECTION to `database`'],
    'a smoke job without the cache store' => [fn (): string => CiExamples::change($workflow(), "      CACHE_STORE: database\n", ''), '[drivers] the smoke job does not set CACHE_STORE to `database`'],
    'no smoke job' => [fn (): string => CiHostVariants::withoutGithubJob('smoke'), '[drivers] no smoke job `smoke`'],
    'no mutation run on the changed files' => [fn (): string => CiExamples::change($workflow(), "      - run: bash scripts/mutation.sh --changed\n", ''), '[mutation] job `backend-tests` does not run `bash scripts/mutation.sh --changed`'],
]);

it('detects a smoke run that leaves the production drivers and a pull request that skips the mutation run', function (Closure $yaml, string $reason) use ($guard): void {
    expect(implode("\n", $guard()->inWorkflow(Narrow::string($yaml()))))->toContain($reason);
})->with('workflows whose smoke run or mutation run drifts');

it('accepts a workflow with the drivers and the mutation run, and reads the file', function () use ($guard, $workflow): void {
    $missing = new WorkflowDriversGuard('/no/such/workflow.yml', 'smoke', [], 'backend-tests', 'x');

    expect($guard()->inWorkflow($workflow()))->toBe([])
        ->and(fn (): array => $missing->violations())->toThrow(RuntimeException::class, 'does not exist');
});

it('keeps the drivers of production in the smoke run of the repository, and the mutation run in its pull requests', function () use ($drivers): void {
    $root = dirname(__DIR__, 2);

    expect(new WorkflowDriversGuard($root . '/.github/workflows/kit-checks.yml', 'smoke', $drivers, 'backend-tests', 'bash scripts/mutation.sh --changed')->violations())->toBe([]);
});
