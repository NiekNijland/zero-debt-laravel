<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

$files = ['composer.json', 'composer.lock', '.env.example', 'compose.yaml', 'README.md', 'scripts/kit-config.sh', 'scripts/ci-commit-messages.sh', '.github/CODEOWNERS', 'renovate.json', '.github/workflows/kit-checks.yml', '.github/workflows/kit-sync.yml', 'scripts/init.sh'];

$sandbox = static function (int $composerStatus = 0) use ($files): string {
    $root = dirname(__DIR__, 2);
    $copy = [];

    foreach ($files as $file) {
        $copy[$file] = (string) file_get_contents($root . '/' . $file);
    }

    $copy['tests/Unit/InitScriptTest.php'] = "<?php\n";
    $copy['bin/composer'] = "#!/usr/bin/env bash\necho \"\$@\" >> \"\$(dirname \"\$0\")/composer.log\"\nexit " . $composerStatus . "\n";
    $path = SourceSandbox::create($copy);
    chmod($path . '/bin/composer', 0755);

    return $path;
};

$init = static function (string $root, string ...$arguments): Process {
    $process = new Process(['bash', 'scripts/init.sh', ...$arguments], $root, ['INIT_COMPOSER' => $root . '/bin/composer']);
    $process->run();

    return $process;
};

$arguments = ['--name', 'acme-api', '--vendor', 'acme', '--ticket', 'ACME', '--database-image', 'postgres:18', '--base-branch', 'develop', '--owner', '@acme/platform'];

dataset('arguments the script refuses', [
    'a name that is not kebab-case' => [['--name', 'Acme_API', '--owner', '@me'], 'is not kebab-case'],
    'no name when nobody can be asked' => [['--owner', '@me'], '--name is required'],
    'no owner when nobody can be asked' => [['--name', 'acme'], '--owner is required'],
    'a ticket prefix in lower case' => [['--name', 'acme', '--owner', '@me', '--ticket', 'proj'], 'is NOTICKET or upper-case letters'],
    'an engine that is not PostgreSQL' => [['--name', 'acme', '--owner', '@me', '--database-image', 'mysql:8.4'], 'is not postgres:<major>'],
    'a branch with a space' => [['--name', 'acme', '--owner', '@me', '--base-branch', 'my branch'], 'is not a simple branch name'],
    'an owner without the at sign' => [['--name', 'acme', '--owner', 'me'], 'is not @user or @org/team'],
    'an owner that is a bot' => [['--name', 'acme', '--owner', '@renovate-bot'], 'looks like a bot'],
    'an owner that is the placeholder' => [['--name', 'acme', '--owner', '@OWNER'], 'is the placeholder'],
    'a vendor with a capital' => [['--name', 'acme', '--owner', '@me', '--vendor', 'Acme'], 'composer vendor'],
    'an argument it does not know' => [['--frobnicate'], 'unknown argument'],
]);

it('refuses a value that does not fit, and changes nothing', function (array $given, string $reason) use ($sandbox, $init, $files): void {
    $root = $sandbox();
    $before = array_map(static fn (string $file): string => (string) file_get_contents($root . '/' . $file), $files);

    try {
        $process = $init($root, ...Narrow::strings($given));

        expect($process->getExitCode())->not->toBe(0)
            ->and($process->getErrorOutput())->toContain($reason)
            ->and(array_map(static fn (string $file): string => (string) file_get_contents($root . '/' . $file), $files))->toBe($before);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('arguments the script refuses');

it('prints every change on a dry run and writes nothing', function () use ($sandbox, $init, $arguments, $files): void {
    $root = $sandbox();
    $before = array_map(static fn (string $file): string => (string) file_get_contents($root . '/' . $file), $files);

    try {
        $process = $init($root, '--dry-run', ...$arguments);

        expect($process->getExitCode())->toBe(0)
            ->and($process->getOutput())->toContain('would set  composer.json: name acme/acme-api')
            ->and($process->getOutput())->toContain('would set  scripts/kit-config.sh: DEFAULT_BASE_BRANCH=develop, DATABASE_IMAGE=postgres:18')
            ->and($process->getOutput())->toContain('would set  .github/CODEOWNERS: owner @acme/platform')
            ->and($process->getOutput())->toContain('dry run done')
            ->and(array_map(static fn (string $file): string => (string) file_get_contents($root . '/' . $file), $files))->toBe($before)
            ->and(is_file($root . '/bin/composer.log'))->toBeFalse();
    } finally {
        SourceSandbox::remove($root);
    }
});

it('sets the name, ticket, image, branch and owner in every file that holds them, and removes itself', function () use ($sandbox, $init, $arguments): void {
    $root = $sandbox();

    try {
        $process = $init($root, ...$arguments);
        $read = static fn (string $file): string => (string) file_get_contents($root . '/' . $file);

        expect($process->getExitCode())->toBe(0)
            ->and($read('composer.json'))->toContain('"name": "acme/acme-api"')
            ->and($read('.env.example'))->toContain("APP_NAME=acme-api\n")
            ->and($read('README.md'))->toStartWith("# acme-api\n")
            ->and($read('README.md'))->not->toContain('init:start')
            ->and($read('README.md'))->not->toContain('scripts/init.sh')
            ->and($read('scripts/kit-config.sh'))->toContain('DEFAULT_BASE_BRANCH="develop"')
            ->and($read('scripts/kit-config.sh'))->toContain('DATABASE_IMAGE="postgres:18"')
            ->and($read('scripts/ci-commit-messages.sh'))->toContain("TICKET='ACME-[0-9]+'")
            ->and($read('scripts/ci-commit-messages.sh'))->toContain("EXAMPLE='ACME-123 feat: add the export'")
            ->and($read('.github/CODEOWNERS'))->not->toContain('@NiekNijland')
            ->and($read('compose.yaml'))->toContain('    image: postgres:18')
            ->and($read('README.md'))->not->toContain('badge.svg')
            ->and($read('.github/CODEOWNERS'))->toContain('/scripts/ @acme/platform')
            ->and($read('renovate.json'))->toContain('"commitMessagePrefix": "ACME-0 chore(deps):"')
            ->and($read('.github/workflows/kit-checks.yml'))->toContain("branches: ['develop']")
            ->and($read('.github/workflows/kit-checks.yml'))->not->toContain('image: postgres:17')
            ->and($read('.github/workflows/kit-sync.yml'))->toContain('ACME-0 chore(kit): move the kit pin')
            ->and($read('.github/workflows/kit-sync.yml'))->not->toContain('NOTICKET')
            ->and($read('bin/composer.log'))->toContain('update --lock')
            ->and($read('bin/composer.log'))->toContain('validate --strict')
            ->and(is_file($root . '/scripts/init.sh'))->toBeFalse()
            ->and(is_file($root . '/tests/Unit/InitScriptTest.php'))->toBeFalse()
            ->and($process->getOutput())->toContain('[ratchet: script] instantiate the template');
    } finally {
        SourceSandbox::remove($root);
    }
});

it('keeps NOTICKET and the defaults when only the name and owner are given', function () use ($sandbox, $init): void {
    $root = $sandbox();

    try {
        $process = $init($root, '--name', 'solo', '--owner', '@me');
        $read = static fn (string $file): string => (string) file_get_contents($root . '/' . $file);

        expect($process->getExitCode())->toBe(0)
            ->and($read('composer.json'))->toContain('"name": "me/solo"')
            ->and($read('scripts/ci-commit-messages.sh'))->toContain("TICKET='NOTICKET'")
            ->and($read('renovate.json'))->toContain('"commitMessagePrefix": "NOTICKET chore(deps):"')
            ->and($read('.github/workflows/kit-sync.yml'))->toContain('NOTICKET chore(kit): move the kit pin')
            ->and($read('.github/CODEOWNERS'))->toContain('/scripts/ @me');
    } finally {
        SourceSandbox::remove($root);
    }
});

it('is run again with the same arguments after it stopped half way', function () use ($sandbox, $init, $arguments): void {
    $root = $sandbox(1);

    try {
        $first = $init($root, ...$arguments);
        $edited = (string) file_get_contents($root . '/scripts/kit-config.sh');
        $kept = is_file($root . '/scripts/init.sh');
        file_put_contents($root . '/bin/composer', "#!/usr/bin/env bash\nexit 0\n");
        $second = $init($root, ...$arguments);

        expect($first->getExitCode())->not->toBe(0)
            ->and($kept)->toBeTrue()
            ->and($edited)->toContain('DATABASE_IMAGE="postgres:18"')
            ->and($second->getExitCode())->toBe(0)
            ->and((string) file_get_contents($root . '/scripts/kit-config.sh'))->toBe($edited);
    } finally {
        SourceSandbox::remove($root);
    }
});
