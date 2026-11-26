<?php

declare(strict_types=1);

use Tests\Support\Architecture\OnePatternRubricGuard;
use Tests\Support\Narrow;

uses()->group('arch');

$guide = "# Guide\n\n## One pattern\n\n| Concern | The single way | Copy this |\n| --- | --- | --- |\n| Action | a class | `a` |\n| Controller | single action | `b` |\n\n- A guard only shrinks.\n";
$rubric = "# Rubric\n\n## What CI enforces\n\n| Row | Guard | Review only |\n| --- | --- | --- |\n| Action | a test | |\n| Controller | a test | |\n\nHouse rules without a row:\n\n| Rule | Guard | Review only |\n| --- | --- | --- |\n| Other | a test | |\n";

dataset('a guide and a rubric that disagree', [
    'a row the rubric lacks' => [fn (string $guide): string => str_replace("| Controller | single action | `b` |\n", "| Controller | single action | `b` |\n| Model | casts | `c` |\n", $guide), null, 'the row "Model" of the one-pattern table is not a row of the rubric table'],
    'a rubric row the guide dropped' => [null, fn (string $rubric): string => str_replace("| Controller | a test | |\n", "| Controller | a test | |\n| Route | a test | |\n", $rubric), 'the rubric row "Route" is no row of the one-pattern table'],
    'rows in another order' => [null, fn (string $rubric): string => str_replace("| Action | a test | |\n| Controller | a test | |\n", "| Controller | a test | |\n| Action | a test | |\n", $rubric), 'the rubric rows are not in the order of the one-pattern table'],
    'a guide with no table' => [fn (string $guide): string => str_replace('## One pattern', '## Patterns', $guide), null, 'the guide has no table under the heading `## One pattern`'],
]);

it('detects a one-pattern table and a rubric table that do not list the same rows', function (?Closure $changeGuide, ?Closure $changeRubric, string $reason) use ($guide, $rubric): void {
    $violations = new OnePatternRubricGuard('## One pattern', '## What CI enforces')->violations($changeGuide instanceof Closure ? Narrow::string($changeGuide($guide)) : $guide, $changeRubric instanceof Closure ? Narrow::string($changeRubric($rubric)) : $rubric);

    expect(implode("\n", $violations))->toContain($reason);
})->with('a guide and a rubric that disagree');

it('accepts the same rows in the same order, and ignores a second table of the rubric', function () use ($guide, $rubric): void {
    expect(new OnePatternRubricGuard('## One pattern', '## What CI enforces')->violations($guide, $rubric))->toBe([]);
});

it('lists every row of the one-pattern table of AGENTS.md in the rubric', function (): void {
    $root = dirname(__DIR__, 2);

    expect(new OnePatternRubricGuard('## One pattern', '## What CI already enforces, and what is review only')->violations((string) file_get_contents($root . '/AGENTS.md'), (string) file_get_contents($root . '/docs/review-rubric.md')))->toBe([]);
});
