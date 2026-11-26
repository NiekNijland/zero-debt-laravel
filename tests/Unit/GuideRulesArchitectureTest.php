<?php

declare(strict_types=1);

use Tests\Support\Architecture\AnalyserPathsGuard;
use Tests\Support\Architecture\BlameRevisionsGuard;
use Tests\Support\Architecture\CodeOwnersGuard;
use Tests\Support\Architecture\ComposerScriptsGuard;
use Tests\Support\Architecture\DocumentedPathGuard;
use Tests\Support\Architecture\GuardGroupGuard;
use Tests\Support\Architecture\GuideRulesGuard;
use Tests\Support\Architecture\OnePatternRubricGuard;
use Tests\Support\Architecture\TaskFlagsGuard;
use Tests\Support\Architecture\WorkflowDriversGuard;
use Tests\Support\Narrow;
use Tests\Support\SourceSandbox;

uses()->group('arch');

$rows = "| Rule id | The guide must say | Instead |\n| --- | --- | --- |\n| time-limits | `timeout-minutes`; every step | give the job and every step a timeout-minutes |\n";

dataset('rules and guides that disagree', [
    'a rule without a row' => [['time-limits', 'pinning'], "A job sets timeout-minutes. Every step has one.\n", 'rule pinning has no row'],
    'a row without a rule' => [[], "A job sets timeout-minutes. Every step has one.\n", 'row time-limits has no rule in the detector'],
    'words the guide lost' => [['time-limits'], "A job sets a limit.\n", 'rule time-limits: the guides no longer say "timeout-minutes"'],
    'half of the words' => [['time-limits'], "A job sets timeout-minutes.\n", 'rule time-limits: the guides no longer say "every step"'],
]);

dataset('tables that are not one row per rule', [
    'a row inside a fenced block' => ["| Rule id | The guide must say | Instead |\n| --- | --- | --- |\n```\n| time-limits | `timeout-minutes` | set it |\n```\n", 'rule time-limits has no row'],
    'a row inside an indented block' => ["| Rule id | The guide must say | Instead |\n| --- | --- | --- |\n\n    | time-limits | `timeout-minutes` | set it |\n", 'rule time-limits has no row'],
    'a rule id that appears twice' => [$rows . "| time-limits | `a limit` | set it |\n", 'row time-limits appears twice'],
    'a row that names no words' => ["| Rule id | The guide must say | Instead |\n| --- | --- | --- |\n| time-limits | | set it |\n", 'row time-limits names no words'],
]);

it('reads a table row only outside a code block and only once', function (string $text, string $reason): void {
    $root = SourceSandbox::create(['docs/guards/rules.md' => $text, 'AGENTS.md' => "A job sets timeout-minutes. Every step has one.\n"]);

    try {
        expect(implode("\n", new GuideRulesGuard($root . '/docs/guards/rules.md', [$root . '/AGENTS.md'], ['time-limits'])->violations()))->toContain('[guide-rules] ' . $reason);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('tables that are not one row per rule');

it('fails a guide that lost the words of a rule, and a rule and a row that do not match', function (array $ruleIds, string $guide, string $reason) use ($rows): void {
    $root = SourceSandbox::create(['docs/guards/rules.md' => $rows, 'AGENTS.md' => $guide]);

    try {
        $violations = new GuideRulesGuard($root . '/docs/guards/rules.md', [$root . '/AGENTS.md'], Narrow::strings($ruleIds))->violations();

        expect($violations)->not->toBe([])
            ->and(implode("\n", $violations))->toContain('[guide-rules] ' . $reason);
    } finally {
        SourceSandbox::remove($root);
    }
})->with('rules and guides that disagree');

it('accepts words spread over two guides, other spacing and a table with its header rows', function () use ($rows): void {
    $root = SourceSandbox::create([
        'docs/guards/rules.md' => $rows . "Text, and | not | a | row | of five cells\n",
        'AGENTS.md' => "A job sets\ntimeout-minutes.\n",
        'docs/review-rubric.md' => "every   step\nhas one.\n",
    ]);

    try {
        expect(new GuideRulesGuard($root . '/docs/guards/rules.md', [$root . '/AGENTS.md', $root . '/docs/review-rubric.md'], ['time-limits'])->violations())->toBe([]);
    } finally {
        SourceSandbox::remove($root);
    }
});

it('keeps the guide words of every rule of the CI guards', function (): void {
    $root = dirname(__DIR__, 2);
    $rules = [
        'github-workflow',
        ...WorkflowDriversGuard::RULES,
        ...TaskFlagsGuard::RULES,
        ...CodeOwnersGuard::RULES,
        ...DocumentedPathGuard::RULES,
        ...GuideRulesGuard::RULES,
        ...OnePatternRubricGuard::RULES,
        ...GuardGroupGuard::RULES,
        ...AnalyserPathsGuard::RULES,
        ...ComposerScriptsGuard::RULES,
        ...BlameRevisionsGuard::RULES,
    ];

    expect(new GuideRulesGuard($root . '/docs/guards/ci-contract.md', [$root . '/docs/ci.md', $root . '/docs/review-rubric.md', $root . '/AGENTS.md'], $rules)->violations())->toBe([]);
});
