<?php

declare(strict_types=1);

namespace Tests\Support\Ci;

use LogicException;

final class CiExamples
{
    public static function checks(): string
    {
        return <<<'SH_WRAP'
        PARTS=(
            "gate::"
            "static::Format|Refactor|Analyser|Type coverage|Lint"
            "guards::Guard tests"
            "repo::Audit|Commit messages|Secret scan"
        )
        set -euo pipefail
        bash "$GUARD/ci-ratchets.sh" --guard scripts/ci-ratchets.sh
        bash scripts/ci-ratchets.sh
        bash scripts/debt-dashboard.sh $DASHBOARD_MODE --offline --details
        TASKS=(
            "Format::vendor/bin/pint --test"
            "Refactor::vendor/bin/rector --dry-run"
            "Analyser::vendor/bin/phpstan analyse"
            "Type coverage::vendor/bin/pest --type-coverage --min=100"
            "Lint::npx eslint . --max-warnings 0"
            "Guard tests::vendor/bin/pest --group arch"
        )
        if [ "$RUN" = "--main" ]; then
            TASKS+=(
                "Audit::composer audit --locked --no-dev --format=json | bash scripts/audit-gate.sh"
            )
        else
            TASKS+=(
                "Audit::composer audit --locked --format=json | bash scripts/audit-gate.sh"
            )
        fi
        TASKS+=(
            "Commit messages::bash scripts/ci-commit-messages.sh"
            "Secret scan::gitleaks git --redact --no-banner"
        )
        bash scripts/ci-parallel.sh "${TASKS[@]}"
        SH_WRAP . "\n";
    }

    public static function kitConfig(): string
    {
        return "DEFAULT_BASE_BRANCH=\"develop\"\nREMOTE=\"origin\"\n";
    }

    public static function analyserConfig(): string
    {
        return "includes:\n    - vendor/phpstan/phpstan/conf/bleedingEdge.neon\n    - vendor/larastan/larastan/extension.neon\n\nparameters:\n    paths:\n        - app\n        - bootstrap/app.php\n        - public\n    level: 10\n";
    }

    public static function testsAnalyserConfig(): string
    {
        return "includes:\n    - phpstan.neon\n\nparameters:\n    paths:\n        - tests\n";
    }

    /**
     * @return array<string, mixed>
     */
    public static function composer(): array
    {
        return [
            'scripts' => [
                'test' => ['Composer\\Config::disableProcessTimeout', 'bash scripts/checks.sh'],
                'test:lint' => ['Composer\\Config::disableProcessTimeout', 'vendor/bin/pint --test', 'vendor/bin/rector process --dry-run --no-progress-bar'],
                'test:types' => ['vendor/bin/phpstan analyse --no-progress'],
                'test:type-coverage' => ['vendor/bin/pest --type-coverage --min=100'],
                'test:unit' => ['vendor/bin/pest --parallel --coverage-clover=coverage.xml', 'bash scripts/checks.sh --coverage coverage.xml'],
                'lint' => ['vendor/bin/rector process --no-progress-bar', 'vendor/bin/pint'],
                'post-autoload-dump' => ['@php artisan package:discover --ansi'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function renovate(): array
    {
        return [
            'extends' => ['config:recommended', 'helpers:pinGitHubActionDigests'],
            'rangeStrategy' => 'bump',
            'commitMessagePrefix' => 'NOTICKET chore(deps):',
            'packageRules' => [['matchUpdateTypes' => ['major'], 'enabled' => false]],
        ];
    }

    public static function codeOwners(): string
    {
        return "/scripts/ @lead\n/.github/workflows/ @lead\n/tests/Support/Architecture/ @lead\n/tests/Unit/*ArchitectureTest.php @lead\n/.github/CODEOWNERS @lead\n";
    }

    /**
     * @return list<string>
     */
    public static function guardedPaths(): array
    {
        return ['scripts/checks.sh', 'scripts/kit-config.sh', 'scripts/debt-caps.json', 'scripts/ci-ratchets.sh', '.github/workflows/kit-checks.yml', 'tests/Support/Architecture/CiSafetyGuard.php', 'tests/Unit/CiContractArchitectureTest.php', '.github/CODEOWNERS'];
    }

    public static function expand(string $text): string
    {
        return strtr($text, ['{is}' => '=' . '=', '{isnot}' => '!' . '=']);
    }

    public static function change(string $source, string $from, string $to): string
    {
        $from = self::expand($from);

        throw_unless(str_contains($source, $from), LogicException::class, 'The example does not hold the text it should change: ' . $from);

        return str_replace($from, self::expand($to), $source);
    }

    public static function changeAfter(string $source, string $marker, string $from, string $to): string
    {
        $from = self::expand($from);
        $at = mb_strpos($source, $marker);
        $in = $at === false ? false : mb_strpos($source, $from, $at);

        throw_if($in === false, LogicException::class, 'The example does not hold the text it should change after ' . $marker . ': ' . $from);

        return mb_substr($source, 0, $in) . self::expand($to) . mb_substr($source, $in + mb_strlen($from));
    }
}
