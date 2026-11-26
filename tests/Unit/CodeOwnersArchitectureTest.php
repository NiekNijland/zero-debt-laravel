<?php

declare(strict_types=1);

use Tests\Support\Architecture\CodeOwnersGuard;
use Tests\Support\CiExamples;
use Tests\Support\Narrow;
use Tests\Support\RecursiveFiles;

uses()->group('arch');

dataset('code-owners files that leave a guard file without a human review', [
    'the scripts not covered' => [fn (): string => CiExamples::change(CiExamples::codeOwners(), "/scripts/ @lead\n", ''), '[code-owners] scripts/debt-caps.json is covered by no code-owners rule'],
    'the workflow directory not covered' => [fn (): string => CiExamples::change(CiExamples::codeOwners(), "/.github/workflows/ @lead\n", ''), '[code-owners] .github/workflows/kit-checks.yml is covered by no'],
    'the guard folder not covered' => [fn (): string => CiExamples::change(CiExamples::codeOwners(), "/tests/Support/Architecture/ @lead\n", ''), '[code-owners] tests/Support/Architecture/CiSafetyGuard.php is covered by no'],
    'the thin tests not covered' => [fn (): string => CiExamples::change(CiExamples::codeOwners(), "/tests/Unit/*ArchitectureTest.php @lead\n", ''), '[code-owners] tests/Unit/CiContractArchitectureTest.php is covered by no'],
    'the file not covering itself' => [fn (): string => CiExamples::change(CiExamples::codeOwners(), "/.github/CODEOWNERS @lead\n", ''), '[code-owners] .github/CODEOWNERS is covered by no'],
    'a later rule that takes a script out of the review' => [fn (): string => CiExamples::codeOwners() . "/scripts/kit-config.sh\n", '[code-owners] scripts/kit-config.sh is matched last by a rule with no owner'],
    'a pattern that matches nothing' => [fn (): string => CiExamples::change(CiExamples::codeOwners(), "/scripts/ @lead\n", "/script/ @lead\n"), '[code-owners] scripts/checks.sh is covered by no'],
    'a negated rule' => [fn (): string => CiExamples::codeOwners() . "!/scripts/checks.sh\n", '[code-owners] the pattern `!/scripts/checks.sh` negates a rule'],
    'a bot as the only owner' => [fn (): string => CiExamples::change(CiExamples::codeOwners(), "/scripts/ @lead\n", "/scripts/ @release-bot\n"), '[code-owners] scripts/checks.sh is owned by a bot only'],
    'an automation account as the only owner' => [fn (): string => CiExamples::change(CiExamples::codeOwners(), "/scripts/ @lead\n", "/scripts/ @renovate[bot]\n"), '[code-owners] scripts/checks.sh is owned by a bot only'],
    'a section header and a comment in place of a rule' => [fn (): string => "[Section]\n# the scripts\n" . CiExamples::change(CiExamples::codeOwners(), "/scripts/ @lead\n", ''), '[code-owners] scripts/checks.sh is covered by no'],
]);

it('detects a code-owners file that leaves a guard file without the review of its owner', function (Closure $codeOwners, string $reason): void {
    $violations = new CodeOwnersGuard('', CiExamples::guardedPaths())->inCodeOwners(Narrow::string($codeOwners()));

    expect(implode("\n", $violations))->toContain($reason);
})->with('code-owners files that leave a guard file without a human review');

it('accepts a code-owners file that covers every guard file, wildcards and a trailing comment, and a host that enforces the owners outside the repository', function (): void {
    $wildcard = "# the guard files\n/scripts/* @lead # the owner\n**/workflows/ @lead\n/tests/Support/Architecture/*.php @lead\n/tests/Unit/?*ArchitectureTest.php @lead\n/.github/CODEOWNERS @lead\n/.github/** @lead\n";

    $withBot = CiExamples::change(CiExamples::codeOwners(), "/scripts/ @lead\n", "/scripts/ @lead @release-bot\n");

    expect(new CodeOwnersGuard('', CiExamples::guardedPaths())->inCodeOwners(CiExamples::codeOwners()))->toBe([])
        ->and(new CodeOwnersGuard('', CiExamples::guardedPaths())->inCodeOwners($withBot))->toBe([])
        ->and(new CodeOwnersGuard('', CiExamples::guardedPaths())->inCodeOwners($wildcard))->toBe([])
        ->and(new CodeOwnersGuard('/no/such/CODEOWNERS', CiExamples::guardedPaths(), hostEnforced: true)->violations())->toBe([])
        ->and(implode("\n", new CodeOwnersGuard('/no/such/CODEOWNERS', CiExamples::guardedPaths())->violations()))->toContain('[code-owners] no code-owners file');
});

it('reads the code-owners file of the repository and covers every file that carries the guards', function (): void {
    $root = dirname(__DIR__, 2);
    $guarded = [];

    foreach (['scripts', 'tests/Support/Architecture', '.github/workflows'] as $folder) {
        foreach (RecursiveFiles::in($root . '/' . $folder) as $file) {
            $guarded[] = mb_substr($file->getPathname(), mb_strlen($root) + 1);
        }
    }

    $thin = Narrow::strings(glob($root . '/tests/Unit/*ArchitectureTest.php'));
    $guarded = [...$guarded, ...array_map(static fn (string $file): string => mb_substr($file, mb_strlen($root) + 1), $thin), '.github/CODEOWNERS', '.git-blame-ignore-revs', 'renovate.json', '.zero-debt-kit-version'];

    expect($guarded)->toContain('scripts/checks.sh')
        ->and(new CodeOwnersGuard($root . '/.github/CODEOWNERS', $guarded)->violations())->toBe([]);
});
