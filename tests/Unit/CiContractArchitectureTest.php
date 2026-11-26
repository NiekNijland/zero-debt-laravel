<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\Support\Architecture\AnalyserPathsGuard;
use Tests\Support\Architecture\BlameRevisionsGuard;
use Tests\Support\Architecture\CiSafetyGuard;
use Tests\Support\Architecture\ComposerScriptsGuard;
use Tests\Support\Architecture\GuardGroupGuard;
use Tests\Support\Architecture\TaskFlagsGuard;
use Tests\Support\Architecture\YamlNode;
use Tests\Support\Ci\CiExamples;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

uses()->group('arch');

dataset('checks scripts that weaken the checks step', [
    'a task that is gone' => [fn (): string => CiExamples::change(CiExamples::checks(), '    "Lint::npx eslint . --max-warnings 0"' . "\n", ''), 'no task named "Lint"'],
    'the ratchets after the dashboard' => [fn (): string => CiExamples::change(CiExamples::checks(), "bash scripts/ci-ratchets.sh\nbash scripts/debt-dashboard.sh \$DASHBOARD_MODE --offline --details\n", "bash scripts/debt-dashboard.sh \$DASHBOARD_MODE --offline --details\nbash scripts/ci-ratchets.sh\n"), 'does not run its four stages in the order'],
    'a dashboard run that is not offline and detailed' => [fn (): string => CiExamples::change(CiExamples::checks(), ' --offline --details', ' --offline'), 'does not run with --offline --details'],
    'a placeholder' => [fn (): string => CiExamples::change(CiExamples::checks(), 'vendor/bin/pint --test', '<formatter in check mode>'), 'still holds a <placeholder>'],
    'a commit' => [fn (): string => CiExamples::change(CiExamples::checks(), 'bash scripts/ci-parallel.sh', "git commit -am autofix\nbash scripts/ci-parallel.sh"), 'a command writes to the repository'],
    'a check that succeeds anyway' => [fn (): string => CiExamples::change(CiExamples::checks(), 'vendor/bin/pint --test"', 'vendor/bin/pint --test || true"'), 'succeed anyway'],
    'a linter that may print warnings' => [fn (): string => CiExamples::change(CiExamples::checks(), 'npx eslint . --max-warnings 0', 'npx eslint .'), 'does not run with `--max-warnings 0`'],
    'a check over the changed files only' => [fn (): string => CiExamples::change(CiExamples::checks(), 'vendor/bin/pint --test', 'vendor/bin/pint --test --dirty'), 'takes a list of changed files'],
    'a check over the diff of version control' => [fn (): string => CiExamples::change(CiExamples::checks(), 'vendor/bin/pint --test', 'vendor/bin/pint --test $(git diff --name-only)'), 'asks version control for a diff'],
    'an install that is no lock-file install' => [fn (): string => CiExamples::change(CiExamples::checks(), 'bash scripts/ci-parallel.sh', "npm install\nbash scripts/ci-parallel.sh"), 'does not come from the lock file'],
    'a lint run with a content cache' => [fn (): string => CiExamples::change(CiExamples::checks(), 'npx eslint . --max-warnings 0', 'npx eslint . --max-warnings 0 --cache'), 'has a content cache'],
    'an audit of a deploying branch that covers the development tools' => [fn (): string => CiExamples::change(CiExamples::checks(), 'composer audit --locked --no-dev', 'composer audit --locked'), 'does not cover production dependencies only'],
    'one audit for both runs' => [fn (): string => CiExamples::change(CiExamples::checks(), 'if [ "$RUN" = "--main" ]; then', 'if [ "$RUN" = "--other" ]; then'), 'no separate "Audit" task'],
]);

it('detects a checks script that weakens the checks step', function (Closure $script, string $reason): void {
    expect(implode("\n", new CiSafetyGuard('develop')->inChecks(Narrow::string($script()), CiExamples::kitConfig())))->toContain($reason);
})->with('checks scripts that weaken the checks step');

it('detects a default base branch that is not the branch pull requests target', function (): void {
    expect(implode("\n", new CiSafetyGuard('main')->inChecks(CiExamples::checks(), CiExamples::kitConfig())))->toContain('DEFAULT_BASE_BRANCH in kit-config.sh is not `main`');
});

it('accepts the checks script, comments that name a banned command and a writing line the owner kept', function (): void {
    $commented = CiExamples::change(CiExamples::checks(), 'set -euo pipefail', "# git push is never run; || true is never written\n# \"Lint::<placeholder in a comment>\"\nset -euo pipefail");
    $kept = CiExamples::change(CiExamples::checks(), 'bash scripts/ci-parallel.sh', "git commit -am autofix\nbash scripts/ci-parallel.sh");

    expect(new CiSafetyGuard('develop')->inChecks(CiExamples::checks(), CiExamples::kitConfig()))->toBe([])
        ->and(new CiSafetyGuard('develop')->inChecks($commented, CiExamples::kitConfig()))->toBe([])
        ->and(new CiSafetyGuard('develop', ['git commit -am autofix'])->inChecks($kept, CiExamples::kitConfig()))->toBe([]);
});

dataset('checks scripts whose task runs without its flag', [
    'a formatter that rewrites' => [fn (): string => CiExamples::change(CiExamples::checks(), 'vendor/bin/pint --test"', 'vendor/bin/pint"'), '[checks-script] the task "Format" runs without `--test`'],
    'a type coverage below the floor' => [fn (): string => CiExamples::change(CiExamples::checks(), '--min=100', '--min=90'), '[checks-script] the task "Type coverage" runs without `--min=100`'],
    'guard tests outside the group' => [fn (): string => CiExamples::change(CiExamples::checks(), 'pest --group arch', 'pest'), '[checks-script] the task "Guard tests" runs without `--group arch`'],
]);

it('detects a task of the checks script that runs without the flag that keeps it on the whole tree', function (Closure $script, string $reason): void {
    expect(implode("\n", new TaskFlagsGuard(['Format' => '--test', 'Type coverage' => '--min=100', 'Guard tests' => '--group arch'])->inChecks(Narrow::string($script()))))->toContain($reason);
})->with('checks scripts whose task runs without its flag');

it('accepts a task that carries its flag, and a script that has no such task', function (): void {
    $guard = new TaskFlagsGuard(['Format' => '--test', 'Missing' => '--x']);

    expect($guard->inChecks(CiExamples::checks()))->toBe([]);
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

it('keeps the pipeline, the checks script and the task runner as the contract says', function () use ($groupGuard, $analyserGuard, $scriptsGuard): void {
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
        ->and(new CiSafetyGuard($base, zeroWarnings: '')->inChecks($checks, $config))->toBe([])
        ->and($groupGuard($root . '/tests/Unit', [])->violations($checks))->toBe([])
        ->and($analyserGuard($folders, $includes)->violations([$root . '/phpstan.neon', $root . '/phpstan-tests.neon']))->toBe([])
        ->and($scriptsGuard()->inProject($composer, $checks, $bot, $workflows))->toBe([])
        ->and(new BlameRevisionsGuard($root)->violations($root . '/.git-blame-ignore-revs'))->toBe([]);
});
