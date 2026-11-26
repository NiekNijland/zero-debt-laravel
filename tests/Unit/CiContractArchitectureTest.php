<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\Support\Architecture\AnalyserPathsGuard;
use Tests\Support\Architecture\BlameRevisionsGuard;
use Tests\Support\Architecture\CiSafetyGuard;
use Tests\Support\Architecture\ComposerScriptsGuard;
use Tests\Support\Architecture\GithubWorkflowGuard;
use Tests\Support\Architecture\GuardGroupGuard;
use Tests\Support\Architecture\YamlNode;
use Tests\Support\CiExamples;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$workflowGuard = static fn (): GithubWorkflowGuard => new GithubWorkflowGuard('', ['main', 'develop'], 'composer test:unit', 'bash scripts/checks.sh', 'bash scripts/mutation.sh', 'smoke', ['CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database', 'DB_CONNECTION' => 'pgsql'], 'postgres:17', ['gate', 'static', 'guards', 'repo']);
$inJob = static fn (string $job, string $from, string $to): string => CiExamples::changeAfter(CiExamples::workflow(), "\n  " . $job . ":\n", $from, $to);
$change = static fn (string $from, string $to): string => CiExamples::change(CiExamples::workflow(), $from, $to);
$again = "  again:\n    runs-on: ubuntu-24.04\n    timeout-minutes: 5\n    steps:\n      - name: Again\n        timeout-minutes: 2\n        run: composer test:unit\n";
$sha = 'actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v4.2.2';

$workflowCases = [
    'no push trigger' => [fn (): string => $change("  push:\n    branches: ['main', 'develop']\n", ''), '[five-runs] the workflow has no `push` trigger'],
    'a path filter on the pull request' => [fn (): string => $change("  pull_request:\n", "  pull_request:\n    paths: ['app/**']\n"), '[five-runs] pull_request has `paths`'],
    'a branch filter, which a stacked pull request does not match' => [fn (): string => $change("  pull_request:\n", "  pull_request:\n    branches: [main]\n"), '[five-runs] pull_request has `branches`'],
    'no re-run after a retarget' => [fn (): string => $change(', edited]', ']'), '[five-runs] pull_request.types lacks `edited`'],
    'a deploying branch that is not listed' => [fn (): string => $change("branches: ['main', 'develop']", "branches: ['main']"), '[five-runs] push.branches does not list the deploying branch develop'],
    'a path filter on the push' => [fn (): string => $change("  push:\n", "  push:\n    paths-ignore: ['docs/**']\n"), '[five-runs] push has a path filter'],
    'a schedule without a cron' => [fn (): string => $change("    - cron: '17 3 * * *'\n", "    - name: nightly\n"), '[five-runs] the schedule trigger has no cron'],
    'a manual trigger' => [fn (): string => $change("on:\n", "on:\n  workflow_dispatch:\n"), '[no-manual-trigger] the trigger `workflow_dispatch`'],
    'a trigger that runs with secrets' => [fn (): string => $change("on:\n", "on:\n  pull_request_target:\n"), '[read-only] pull_request_target is used'],
    'no concurrency group' => [fn (): string => $change("concurrency:\n  group: kit-\${{ github.event_name }}-\${{ github.event.pull_request.number || github.sha }}\n  cancel-in-progress: \${{ github.event_name {is} 'pull_request' }}\n\n", ''), '[read-only] the workflow has no concurrency group'],
    'a group that cancels a push to a deploying branch' => [fn (): string => $change("cancel-in-progress: \${{ github.event_name {is} 'pull_request' }}", 'cancel-in-progress: true'), '[read-only] concurrency cancels runs of every event'],
    'no top-level permissions' => [fn (): string => $change("permissions:\n  contents: read\n\njobs:", 'jobs:'), '[read-only] the workflow has no top-level `permissions`'],
    'a token that writes' => [fn (): string => $change("permissions:\n  contents: read\n\njobs:", "permissions:\n  contents: write\n\njobs:"), '[read-only] a permission is `write`'],
    'a condition on the checks job' => [fn (): string => $inJob('checks', "if: github.event_name {is} 'pull_request'", "if: github.event_name {is} 'pull_request' && github.actor {isnot} 'renovate[bot]'"), '[checks-parts] job `checks` has another condition'],
    'a part without a matrix entry' => [fn (): string => $inJob('checks', 'part: [gate, static, guards, repo]', 'part: [gate, static, guards]'), '[checks-parts] the matrix of job `checks` is not exactly the parts'],
    'a checks step with a condition of its own' => [fn (): string => $change('run: bash scripts/checks.sh --part ${{ matrix.part }}', "if: matrix.part {isnot} 'gate'\n        run: bash scripts/checks.sh --part \${{ matrix.part }}"), '[checks-parts] the checks step of job `checks` has a condition of its own'],
    'no base for the ratchets' => [fn (): string => $inJob('checks', "    env:\n      CHECK_BASE: \${{ github.base_ref }}\n", ''), '[checks-parts] job `checks` does not set CHECK_BASE'],
    'a head that the job does not check out' => [fn (): string => $inJob('checks', 'CHECK_BASE: ${{ github.base_ref }}', "CHECK_BASE: \${{ github.base_ref }}\n      CHECK_HEAD: \${{ github.event.pull_request.head.sha }}"), '[checks-parts] job `checks` sets CHECK_HEAD but does not check out that revision'],
    'no checks on a deploying branch' => [fn (): string => $change('--main --part', '--part'), '[checks-parts] no job runs exactly `bash scripts/checks.sh --main --part ${{ matrix.part }}`'],
    'an aggregate job that passes whatever happened' => [fn (): string => $change('run: test "$RESULT" = success', 'run: echo done'), '[checks-parts] no aggregate job'],
    'an action on a tag' => [fn (): string => $inJob('smoke', $sha, 'actions/checkout@v4'), '[pinning] job `smoke` uses `actions/checkout@v4`, which is not pinned to a full commit sha'],
    'a runner on a moving label' => [fn (): string => $inJob('checks-result', 'runs-on: ubuntu-24.04', 'runs-on: ubuntu-latest'), '[pinning] job `checks-result` runs on a moving label'],
    'a container without a digest' => [fn (): string => $inJob('smoke', "    env:\n      CACHE_STORE", "    container:\n      image: php:8.4\n    env:\n      CACHE_STORE"), '[pinning] job `smoke` runs in a container that is not pinned by digest'],
    'a download without a checksum' => [fn (): string => $change("          echo \"\${GITLEAKS_SHA256}  gitleaks.tgz\" | sha256sum -c -\n", ''), '[pinning] job `checks` downloads a tool and does not check it against a checksum'],
    'a job without a maximum time' => [fn (): string => $inJob('checks-result', "    timeout-minutes: 5\n    steps:", '    steps:'), '[time-limits] job `checks-result` has no integer timeout-minutes'],
    'a step without a maximum time' => [fn (): string => $inJob('checks-result', "        timeout-minutes: 2\n", ''), '[time-limits] job `checks-result`, step `Every part passed` has no integer timeout-minutes'],
    'a checks step that may fail' => [fn (): string => $inJob('checks', "        timeout-minutes: 10\n        run: bash scripts/checks.sh --part", "        timeout-minutes: 10\n        continue-on-error: true\n        run: bash scripts/checks.sh --part"), '[nothing-fails] job `checks`, a step may fail'],
    'a command that succeeds anyway' => [fn (): string => $inJob('checks', 'run: composer install', 'run: composer install || true'), '[checks-script] a check is made to succeed anyway'],
    'a job that is switched off' => [fn (): string => $inJob('smoke', "if: github.event_name {isnot} 'schedule'", 'if: false'), '[nothing-fails] job `smoke` may fail or is switched off'],
    'a command that commits' => [fn (): string => $inJob('scheduled', 'run: bash scripts/mutation.sh', "run: |\n          bash scripts/mutation.sh\n          git commit -am autofix"), '[read-only] a command writes to the repository'],
    'a service image that is not the production engine' => [fn (): string => $inJob('backend-tests', 'image: postgres:17', 'image: postgres:16'), '[database-and-drivers] job `backend-tests`, service `database` image is not the database image'],
    'a service without a health check' => [fn (): string => $inJob('smoke', "        options: >-\n          --health-cmd \"pg_isready -U postgres\"\n          --health-interval 5s --health-timeout 5s --health-retries 12\n", ''), '[database-and-drivers] job `smoke`, service `database` has no env or no health check'],
    'a service the runner cannot reach' => [fn (): string => $inJob('smoke', "        ports:\n          - 5432:5432\n", ''), '[database-and-drivers] job `smoke`, service `database` publishes no port'],
    'a smoke job on another queue driver' => [fn (): string => $inJob('smoke', 'QUEUE_CONNECTION: database', 'QUEUE_CONNECTION: sync'), '[database-and-drivers] the smoke job does not set QUEUE_CONNECTION to `database`'],
    'no smoke job' => [fn (): string => $change("\n  smoke:\n", "\n  other:\n"), '[database-and-drivers] no smoke job `smoke`'],
    'an install that updates' => [fn (): string => $inJob('checks', 'run: composer install', 'run: composer update'), '[lock-installs] job `checks` installs or updates a dependency outside the lock file'],
    'a scheduled run without the mutation run' => [fn (): string => $inJob('scheduled', "        run: bash scripts/mutation.sh\n", "        run: echo mutation\n"), '[scheduled-run] job `scheduled` does not run the whole-tree mutation run'],
    'the scheduled checks on another event' => [fn (): string => $inJob('scheduled', "if: github.event_name {is} 'schedule'", "if: github.event_name {is} 'schedule' || github.event_name {is} 'push'"), '[scheduled-run] job `scheduled` runs the scheduled checks on another condition'],
    'no scheduled checks' => [fn (): string => $change('checks.sh --scheduled', 'checks.sh --main'), '[scheduled-run] no job runs exactly `bash scripts/checks.sh --scheduled`'],
    'no mutation run on the pull request' => [fn (): string => $change('bash scripts/mutation.sh --changed', 'bash scripts/mutation.sh'), '[mutation] job `backend-tests` does not run the mutation tests on the changed files'],
    'a shallow clone' => [fn (): string => $inJob('smoke', 'fetch-depth: 0', 'fetch-depth: 1'), '[full-history] job `smoke` checks out with a shallow clone'],
    'credentials that the checkout drops' => [fn (): string => $inJob('checks', 'fetch-depth: 0', "fetch-depth: 0\n          persist-credentials: false"), '[full-history] job `checks` checks out with `persist-credentials: false`'],
    'the tests twice on one event' => [fn (): string => CiExamples::workflow() . $again, '[one-suite-per-trigger] the backend tests run 2 times on one pull_request event'],
];

dataset('GitHub workflows that narrow a check or skip a run', $workflowCases);

it('detects a GitHub workflow that narrows a check or skips a run', function (Closure $workflow, string $reason) use ($workflowGuard): void {
    expect(implode("\n", $workflowGuard()->inWorkflows(['kit-checks.yml' => Narrow::string($workflow())])))->toContain($reason);
})->with('GitHub workflows that narrow a check or skip a run');

it('has a failing example for every rule of the workflow guard', function () use ($workflowCases): void {
    $named = array_map(static fn (array $case): string => (string) preg_replace('/^\[([a-z-]+)\].*$/', '$1', Narrow::string($case[1])), $workflowCases);
    $rules = [...GithubWorkflowGuard::RULES, 'no-manual-trigger'];

    expect(array_diff($rules, $named, ['checks-script']))->toBe([]);
});

it('accepts the example workflow, an owner exception, and a deploy workflow that waits for it', function () use ($workflowGuard): void {
    $guard = $workflowGuard();
    $exception = new GithubWorkflowGuard('', ['main', 'develop'], 'composer test:unit', 'bash scripts/checks.sh', 'bash scripts/mutation.sh', 'smoke', ['CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database', 'DB_CONNECTION' => 'pgsql'], 'postgres:17', ['gate', 'static', 'guards', 'repo'], ['workflow_dispatch'], ['git commit -am autofix']);
    $dispatch = CiExamples::change(CiExamples::workflow(), "on:\n", "on:\n  workflow_dispatch:\n");
    $listed = CiExamples::change(CiExamples::workflow(), "on:\n  pull_request:\n    types: [opened, synchronize, reopened, edited]\n  push:\n    branches: ['main', 'develop']\n  schedule:\n    - cron: '17 3 * * *'\n", "on: [pull_request, push, schedule]\n");
    $commit = CiExamples::changeAfter(CiExamples::workflow(), "\n  scheduled:\n", 'run: bash scripts/mutation.sh', "run: |\n          bash scripts/mutation.sh\n          git commit -am autofix");

    expect($guard->inWorkflows(['kit-checks.yml' => CiExamples::workflow()]))->toBe([])
        ->and($guard->inWorkflows(['kit-checks.yml' => CiExamples::workflow(), 'deploy.yml' => CiExamples::deploy()]))->toBe([])
        ->and($exception->inWorkflows(['kit-checks.yml' => $dispatch]))->toBe([])
        ->and($exception->inWorkflows(['kit-checks.yml' => $commit]))->toBe([])
        ->and($guard->inWorkflows(['kit-checks.yml' => CiExamples::workflow(), 'canary.yml' => CiExamples::canary()]))->toBe([])
        ->and(implode("\n", $guard->inWorkflows(['canary.yml' => CiExamples::canary()])))->toContain('no workflow runs `bash scripts/checks.sh`')
        ->and(implode("\n", $guard->inWorkflows(['deploy.yml' => CiExamples::deploy()])))->toContain('no workflow runs `bash scripts/checks.sh`')
        ->and(implode("\n", $guard->inWorkflows(['list.yml' => $listed])))->toContain('[five-runs] push.branches does not list the deploying branch main')
        ->and(implode("\n", $guard->inWorkflows(['broken.yml' => "jobs: [\n"])))->toContain('broken.yml: [five-runs] the workflow is not valid YAML');
});

it('reads the workflow directory, and fails when it holds no workflow', function (): void {
    $root = SourceSandbox::create(['workflows/kit-checks.yml' => CiExamples::workflow(), 'empty/.gitkeep' => '']);

    try {
        $guard = new GithubWorkflowGuard($root . '/workflows', ['main', 'develop'], 'composer test:unit', 'bash scripts/checks.sh', 'bash scripts/mutation.sh', 'smoke', ['CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database', 'DB_CONNECTION' => 'pgsql'], 'postgres:17', ['gate', 'static', 'guards', 'repo']);
        $empty = new GithubWorkflowGuard($root . '/empty', ['main'], 'composer test:unit', 'bash scripts/checks.sh', 'bash scripts/mutation.sh', 'smoke', [], 'postgres:17', ['gate']);

        expect($guard->violations())->toBe([])
            ->and(implode("\n", $empty->violations()))->toContain('[five-runs] no workflow in');
    } finally {
        SourceSandbox::remove($root);
    }
});

$safetyGuard = static fn (string $base = 'develop'): CiSafetyGuard => new CiSafetyGuard($base, ['--guard scripts/ci-ratchets.sh', 'bash scripts/ci-ratchets.sh', 'debt-dashboard.sh $DASHBOARD_MODE --offline', 'bash scripts/ci-parallel.sh'], ['Format', 'Refactor', 'Analyser', 'Type coverage', 'Lint', 'Guard tests', 'Audit', 'Commit messages', 'Secret scan'], ['Format' => '--test', 'Refactor' => '--dry-run', 'Type coverage' => '--min=100', 'Guard tests' => '--group=arch'], '--offline --details', 'composer audit');

dataset('checks scripts that weaken the checks step', [
    'a task that is gone' => [fn (): string => CiExamples::change(CiExamples::checksScript(), "    \"Lint::vendor/bin/parallel-lint app tests\"\n", ''), '[checks-script] the checks script has no task named "Lint"'],
    'the ratchets after the dashboard' => [fn (): string => CiExamples::change(CiExamples::checksScript(), "bash scripts/ci-ratchets.sh\nbash scripts/debt-dashboard.sh \$DASHBOARD_MODE --offline --details\n", "bash scripts/debt-dashboard.sh \$DASHBOARD_MODE --offline --details\nbash scripts/ci-ratchets.sh\n"), '[checks-script] the checks script does not run its stages in the order'],
    'a dashboard run that is not offline and detailed' => [fn (): string => CiExamples::change(CiExamples::checksScript(), ' --offline --details', ' --offline'), '[checks-script] the dashboard line of the checks script does not run with --offline --details'],
    'a placeholder' => [fn (): string => CiExamples::change(CiExamples::checksScript(), 'vendor/bin/pint --test', '<formatter in check mode>'), '[checks-script] the checks script still holds a <placeholder>'],
    'a commit' => [fn (): string => CiExamples::change(CiExamples::checksScript(), 'bash scripts/ci-parallel.sh', "git commit -am autofix\nbash scripts/ci-parallel.sh"), '[read-only] a command writes to the repository'],
    'a check that succeeds anyway' => [fn (): string => CiExamples::change(CiExamples::checksScript(), 'vendor/bin/pint --test"', 'vendor/bin/pint --test || true"'), '[checks-script] a check is made to succeed anyway'],
    'a formatter on the changed files' => [fn (): string => CiExamples::change(CiExamples::checksScript(), 'vendor/bin/pint --test"', 'vendor/bin/pint --test $(git diff --name-only)"'), '[checks-script] a command of the checks script takes a list of changed files'],
    'a formatter that rewrites' => [fn (): string => CiExamples::change(CiExamples::checksScript(), 'vendor/bin/pint --test"', 'vendor/bin/pint"'), '[checks-script] the task "Format" runs without `--test`'],
    'a type coverage below the floor' => [fn (): string => CiExamples::change(CiExamples::checksScript(), '--min=100', '--min=90'), '[checks-script] the task "Type coverage" runs without `--min=100`'],
    'an audit of a deploying branch that covers the dev tools' => [fn (): string => CiExamples::change(CiExamples::checksScript(), ' --no-dev', ''), '[audit-production-only]'],
    'no audit' => [fn (): string => CiExamples::change(CiExamples::checksScript(), 'composer audit', 'composer outdated'), '[audit-production-only] the checks script has no audit'],
]);

it('detects a checks script that weakens the checks step', function (Closure $script, string $reason) use ($safetyGuard): void {
    expect(implode("\n", $safetyGuard()->inChecks(Narrow::string($script()), CiExamples::kitConfig())))->toContain($reason);
})->with('checks scripts that weaken the checks step');

it('detects a default base branch that is not the branch pull requests target', function () use ($safetyGuard): void {
    expect(implode("\n", $safetyGuard('main')->inChecks(CiExamples::checksScript(), CiExamples::kitConfig())))->toContain('[base-branch] DEFAULT_BASE_BRANCH of the shared config is not `main`');
});

it('accepts the checks script, comments that name a banned command and a writing line the owner kept', function () use ($safetyGuard): void {
    $commented = CiExamples::change(CiExamples::checksScript(), 'set -euo pipefail', "# git push is never run; || true is never written\n# \"Lint::<placeholder in a comment>\"\nset -euo pipefail");
    $kept = CiExamples::change(CiExamples::checksScript(), 'bash scripts/ci-parallel.sh', "git commit -am autofix\nbash scripts/ci-parallel.sh");
    $exception = new CiSafetyGuard('develop', ['--guard scripts/ci-ratchets.sh'], [], [], '--offline', 'composer audit', ['git commit -am autofix']);

    expect($safetyGuard()->inChecks(CiExamples::checksScript(), CiExamples::kitConfig()))->toBe([])
        ->and($safetyGuard()->inChecks($commented, CiExamples::kitConfig()))->toBe([])
        ->and($exception->inCommands(['git commit -am autofix']))->toBe([])
        ->and($exception->inChecks($kept, CiExamples::kitConfig()))->toBe([]);
});

$groupGuard = static fn (string $folder = '', array $kept = ['CiContractArchitectureTest.php']): GuardGroupGuard => new GuardGroupGuard($folder, 'ArchitectureTest.php', "->group('arch')", '--group=arch', Narrow::strings($kept));

dataset('guard tests outside the guard group', [
    'a guard test with no group' => [['OneArchitectureTest.php' => "<?php\n\nit('x', fn () => 1);\n"], 'abc', '[guard-group] OneArchitectureTest.php is not in the guard group'],
    'a checks script that does not run the group' => [['OneArchitectureTest.php' => "uses()->group('arch');\n"], 'vendor/bin/pest', '[guard-group] the checks script does not run the guard group'],
]);

it('detects a guard test that is in no group, and a checks script that does not run the group', function (array $sources, string $script, string $reason) use ($groupGuard): void {
    expect(implode("\n", $groupGuard()->inTests(Narrow::stringMap($sources), $script)))->toContain($reason);
})->with('guard tests outside the guard group');

it('accepts guard tests in the group, and one the owner keeps outside it', function () use ($groupGuard): void {
    $root = SourceSandbox::create(['OneArchitectureTest.php' => "uses()->group('arch');\n", 'CiContractArchitectureTest.php' => "<?php\n", 'Other.php' => '<?php']);

    try {
        $guard = new GuardGroupGuard($root, 'ArchitectureTest.php', "->group('arch')", '--group=arch', ['CiContractArchitectureTest.php']);

        expect($guard->violations('vendor/bin/pest --group=arch'))->toBe([])
            ->and($groupGuard()->inTests(['OneArchitectureTest.php' => "uses()->group('arch');"], 'x --group=arch'))->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

$analyserGuard = static fn (array $folders = ['app', 'bootstrap', 'public', 'tests'], array $includes = ['phpstan/phpstan/conf/bleedingEdge.neon', 'larastan/larastan/extension.neon']): AnalyserPathsGuard => new AnalyserPathsGuard(Narrow::strings($folders), Narrow::strings($includes));

dataset('analyser configs that skip a folder or an extension', [
    'a folder the analyser does not read' => [CiExamples::change(CiExamples::analyserConfig(), "        - public\n", ''), '[analyser-paths] the analyser paths do not name `public`'],
    'a source folder named by a file only' => [CiExamples::change(CiExamples::analyserConfig(), "        - bootstrap/app.php\n", ''), '[analyser-paths] the analyser paths do not name `bootstrap`'],
    'no bleeding-edge file' => [CiExamples::change(CiExamples::analyserConfig(), "    - vendor/phpstan/phpstan/conf/bleedingEdge.neon\n", ''), '[analyser-paths] the analyser config does not include `phpstan/phpstan/conf/bleedingEdge.neon`'],
    'an extension that is gone' => [CiExamples::change(CiExamples::analyserConfig(), "    - vendor/larastan/larastan/extension.neon\n", ''), '[analyser-paths] the analyser config does not include `larastan/larastan/extension.neon`'],
]);

it('detects an analyser config that skips a folder or an extension', function (string $config, string $reason) use ($analyserGuard): void {
    expect(implode("\n", $analyserGuard()->inConfigs([$config, CiExamples::testsAnalyserConfig()])))->toContain($reason);
})->with('analyser configs that skip a folder or an extension');

it('accepts the analyser configs read together, and reads the files', function () use ($analyserGuard): void {
    $root = SourceSandbox::create(['phpstan.neon' => CiExamples::analyserConfig(), 'phpstan-tests.neon' => CiExamples::testsAnalyserConfig()]);

    try {
        expect($analyserGuard()->inConfigs([CiExamples::analyserConfig(), CiExamples::testsAnalyserConfig()]))->toBe([])
            ->and($analyserGuard()->violations([$root . '/phpstan.neon', $root . '/phpstan-tests.neon']))->toBe([])
            ->and(implode("\n", $analyserGuard()->violations([$root . '/phpstan.neon'])))->toContain('do not name `tests`');
    } finally {
        SourceSandbox::remove($root);
    }
});

$scriptsGuard = static fn (): ComposerScriptsGuard => new ComposerScriptsGuard(['test', 'test:lint', 'test:types', 'test:type-coverage', 'test:unit', 'lint'], 'test', 'bash scripts/checks.sh', ['test:lint', 'test:types', 'test:type-coverage'], 'lint', 'test:unit', ['extends', 'rangeStrategy', 'commitMessagePrefix', 'packageRules'], ['helpers:pinGitHubActionDigests']);
$scripted = static fn (string $name, mixed $value): array => ['scripts' => [...Narrow::map(CiExamples::composer()['scripts']), $name => $value]];

dataset('task runners and bots that drift from the contract', [
    'a script that is gone' => [fn (): array => ['scripts' => array_diff_key(Narrow::map(CiExamples::composer()['scripts']), ['test:types' => 1])], 'abc', CiExamples::renovate(), [], '[composer-scripts] the task runner has no script `test:types`'],
    'a test script that is not the checks' => [fn (): array => ['scripts' => [...Narrow::map(CiExamples::composer()['scripts']), 'test' => ['vendor/bin/pest']]], 'abc', CiExamples::renovate(), [], '[composer-scripts] the script `test` is not exactly `bash scripts/checks.sh`'],
    'a lint script that runs another command than the checks' => [fn (): array => ['scripts' => [...Narrow::map(CiExamples::composer()['scripts']), 'test:lint' => ['vendor/bin/pint']]], 'vendor/bin/rector', CiExamples::renovate(), [], '[composer-scripts] the script `test:lint` runs `vendor/bin/pint`, which is no line of the checks script'],
    'a workflow that calls the rewriting script' => [CiExamples::composer(...), 'vendor/bin/pint --test vendor/bin/rector process --dry-run --no-progress-bar vendor/bin/phpstan analyse --no-progress vendor/bin/pest --type-coverage --min=100', CiExamples::renovate(), ['run: composer lint'], '[composer-scripts] a workflow calls `composer lint`'],
    'an update on an install event' => [fn (): array => ['scripts' => [...Narrow::map(CiExamples::composer()['scripts']), 'post-update-cmd' => ['@composer bump']]], 'vendor/bin/pint --test vendor/bin/rector process --dry-run --no-progress-bar vendor/bin/phpstan analyse --no-progress vendor/bin/pest --type-coverage --min=100', CiExamples::renovate(), [], '[composer-scripts] the event `post-update-cmd` runs an update or a bump'],
    'a test run that holds no coverage floor' => [fn (): array => ['scripts' => [...Narrow::map(CiExamples::composer()['scripts']), 'test:unit' => ['vendor/bin/pest --parallel']]], 'vendor/bin/pint --test vendor/bin/rector process --dry-run --no-progress-bar vendor/bin/phpstan analyse --no-progress vendor/bin/pest --type-coverage --min=100', CiExamples::renovate(), [], '[composer-scripts] the script `test:unit` does not hold the coverage at the committed floor'],
    'a bot config without a key' => [CiExamples::composer(...), 'vendor/bin/pint --test vendor/bin/rector process --dry-run --no-progress-bar vendor/bin/phpstan analyse --no-progress vendor/bin/pest --type-coverage --min=100', array_diff_key(CiExamples::renovate(), ['rangeStrategy' => 1]), [], "[composer-scripts] the dependency bot's config has no `rangeStrategy`"],
    'a bot that does not pin the actions' => [CiExamples::composer(...), 'vendor/bin/pint --test vendor/bin/rector process --dry-run --no-progress-bar vendor/bin/phpstan analyse --no-progress vendor/bin/pest --type-coverage --min=100', [...CiExamples::renovate(), 'extends' => ['config:recommended']], [], "[composer-scripts] the dependency bot's config does not extend `helpers:pinGitHubActionDigests`"],
]);

it('detects a task runner and a dependency bot that drift from the contract', function (Closure $composer, string $checks, array $bot, array $workflows, string $reason) use ($scriptsGuard): void {
    expect(implode("\n", $scriptsGuard()->inProject(Narrow::map($composer()), $checks, Narrow::map($bot), Narrow::strings($workflows))))->toContain($reason);
})->with('task runners and bots that drift from the contract');

it('accepts the six scripts wired to the checks and a bot that keeps its keys', function () use ($scriptsGuard, $scripted): void {
    $checks = 'vendor/bin/pint --test vendor/bin/rector process --dry-run --no-progress-bar vendor/bin/phpstan analyse --no-progress vendor/bin/pest --type-coverage --min=100';

    expect($scriptsGuard()->inProject(CiExamples::composer(), $checks, CiExamples::renovate(), ['run: composer test:unit']))->toBe([])
        ->and($scriptsGuard()->inProject($scripted('test', ['Composer\\Config::disableProcessTimeout', 'bash scripts/checks.sh']), $checks, CiExamples::renovate(), []))->toBe([]);
});

it('detects a blame revision that is not a commit of this history, and accepts one that is', function (): void {
    $root = SourceSandbox::create(['README.md' => 'x']);
    $git = static fn (string ...$arguments): string => new Process(['git', '-c', 'user.email=a@example.test', '-c', 'user.name=a', ...$arguments], $root)->mustRun()->getOutput();

    try {
        $git('init', '-q');
        $git('add', '.');
        $git('commit', '-q', '-m', 'first');
        $head = mb_trim($git('rev-parse', 'HEAD'));
        $guard = new BlameRevisionsGuard($root);

        expect($guard->inRevisions($head . " # format\n\n"))->toBe([])
            ->and(implode("\n", $guard->inRevisions(str_repeat('a', 40))))->toContain('[blame-revisions] ' . str_repeat('a', 40) . ' is not a commit reachable from the head')
            ->and(implode("\n", $guard->inRevisions('abc123')))->toContain('[blame-revisions] `abc123` is not a full commit hash')
            ->and(implode("\n", $guard->violations($root . '/missing')))->toContain('[blame-revisions] the file of formatting commits that blame skips does not exist');
        file_put_contents($root . '/revisions', $head . "\n");

        expect($guard->violations($root . '/revisions'))->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('reads the YAML of a workflow, a key that a loose parser reads as true, and text that is not YAML', function (): void {
    expect(YamlNode::parse("on: push\njobs: {}\n"))->toBe(['on' => 'push', 'jobs' => []])
        ->and(YamlNode::parse("a: [\n"))->toBe([])
        ->and(YamlNode::has(['a' => ['b' => 1]], 'a', 'b'))->toBeTrue()
        ->and(YamlNode::has(['a' => ['b' => 1]], 'a', 'c'))->toBeFalse()
        ->and(YamlNode::at(['a' => ['b' => 1]], 'a', 'x', 'y'))->toBeNull()
        ->and(YamlNode::text(5))->toBe('5')
        ->and(YamlNode::text(['x']))->toBe('');
});

it('keeps the pipeline, the checks script and the task runner as the contract says', function () use ($safetyGuard, $groupGuard, $analyserGuard, $scriptsGuard): void {
    $root = dirname(__DIR__, 2);
    $config = (string) file_get_contents($root . '/scripts/kit-config.sh');
    $checks = (string) file_get_contents($root . '/scripts/checks.sh');
    $parts = new Process(['bash', 'scripts/checks.sh', '--list-parts'], $root)->mustRun();
    $image = preg_match('/^DATABASE_IMAGE="([^"]+)"/m', $config, $match) === 1 ? $match[1] : '';
    $base = preg_match('/^DEFAULT_BASE_BRANCH="([^"]+)"/m', $config, $branch) === 1 ? $branch[1] : '';
    $workflows = array_map(static fn (string $file): string => (string) file_get_contents($file), Narrow::strings(glob($root . '/.github/workflows/*.yml')));
    $composer = Narrow::map(json_decode((string) file_get_contents($root . '/composer.json'), true, flags: JSON_THROW_ON_ERROR));
    $bot = Narrow::map(json_decode((string) file_get_contents($root . '/renovate.json'), true, flags: JSON_THROW_ON_ERROR));
    $ratchets = (string) file_get_contents($root . '/scripts/ci-ratchets.sh');
    $folders = [...explode(',', preg_match('/^SOURCE_PATHS="([^"]*)"/m', $ratchets, $source) === 1 ? $source[1] : ''), ...explode(',', preg_match('/^TEST_PATHS="([^"]*)"/m', $ratchets, $tests) === 1 ? $tests[1] : ''), 'public'];
    $includes = ['phpstan/phpstan/conf/bleedingEdge.neon', 'larastan/larastan/extension.neon', 'phpstan/phpstan-strict-rules/rules.neon', 'spaze/phpstan-disallowed-calls/extension.neon', 'tomasvotruba/type-coverage/config/extension.neon', 'shipmonk/dead-code-detector/rules.neon'];

    expect($base)->not->toBe('')
        ->and($image)->toStartWith('postgres:')
        ->and(Narrow::strings(explode("\n", mb_trim($parts->getOutput()))))->toBe(['gate', 'static', 'guards', 'repo'])
        ->and(new GithubWorkflowGuard($root . '/.github/workflows', [$base], 'composer test:unit', 'bash scripts/checks.sh', 'bash scripts/mutation.sh', 'smoke', ['CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database', 'DB_CONNECTION' => 'pgsql'], $image, explode("\n", mb_trim($parts->getOutput())))->violations())->toBe([])
        ->and($safetyGuard($base)->inChecks($checks, $config))->toBe([])
        ->and($groupGuard($root . '/tests/Unit', [])->violations($checks))->toBe([])
        ->and($analyserGuard($folders, $includes)->violations([$root . '/phpstan.neon', $root . '/phpstan-tests.neon']))->toBe([])
        ->and($scriptsGuard()->inProject($composer, $checks, $bot, $workflows))->toBe([])
        ->and(new BlameRevisionsGuard($root)->violations($root . '/.git-blame-ignore-revs'))->toBe([]);
});
