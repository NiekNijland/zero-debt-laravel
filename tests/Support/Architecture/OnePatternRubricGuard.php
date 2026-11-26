<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class OnePatternRubricGuard
{
    public const array RULES = ['one-pattern-rubric'];

    public function __construct(
        private string $patternHeading,
        private string $rubricHeading,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(string $guide, string $rubric): array
    {
        $patterns = $this->firstColumn($guide, $this->patternHeading);
        $rows = $this->firstColumn($rubric, $this->rubricHeading);
        $violations = [];

        if ($patterns === []) {
            return ['[one-pattern-rubric] the guide has no table under the heading `' . $this->patternHeading . '`'];
        }

        foreach (array_diff($patterns, $rows) as $missing) {
            $violations[] = '[one-pattern-rubric] the row "' . $missing . '" of the one-pattern table is not a row of the rubric table: say which guard enforces it, or that it is review only';
        }

        foreach (array_diff($rows, $patterns) as $stale) {
            $violations[] = '[one-pattern-rubric] the rubric row "' . $stale . '" is no row of the one-pattern table: remove it, or add the row to the guide';
        }

        if ($violations === [] && $patterns !== $rows) {
            $violations[] = '[one-pattern-rubric] the rubric rows are not in the order of the one-pattern table';
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function firstColumn(string $markdown, string $heading): array
    {
        $table = [];
        $reading = false;

        foreach (explode("\n", $markdown) as $line) {
            if (mb_trim($line) === $heading) {
                $reading = true;

                continue;
            }

            if (! $reading) {
                continue;
            }

            if (str_starts_with($line, '|')) {
                $table[] = mb_trim(explode('|', $line)[1]);
            } elseif ($table !== []) {
                break;
            }
        }

        return array_slice($table, 2);
    }
}
