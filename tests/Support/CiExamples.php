<?php

declare(strict_types=1);

namespace Tests\Support;

use LogicException;

final class CiExamples
{
    public static function workflow(): string
    {
        return self::expand(<<<'YAML'
            name: Kit checks

            on:
              pull_request:
                types: [opened, synchronize, reopened, edited]
              push:
                branches: ['main', 'develop']
              schedule:
                - cron: '17 3 * * *'

            concurrency:
              group: kit-${{ github.event_name }}-${{ github.event.pull_request.number || github.sha }}
              cancel-in-progress: ${{ github.event_name {is} 'pull_request' }}

            permissions:
              contents: read

            jobs:
              checks:
                if: github.event_name {is} 'pull_request'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                strategy:
                  fail-fast: false
                  matrix:
                    part: [gate, static, guards, repo]
                env:
                  CHECK_BASE: ${{ github.base_ref }}
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v4.2.2
                    timeout-minutes: 5
                    with:
                      fetch-depth: 0
                  - name: Install from the lock file
                    timeout-minutes: 5
                    run: composer install
                  - name: Install gitleaks
                    if: matrix.part {is} 'repo'
                    timeout-minutes: 5
                    env:
                      GITLEAKS_VERSION: 8.30.1
                      GITLEAKS_SHA256: 0000000000000000000000000000000000000000000000000000000000000000
                    run: |
                      curl -sSfL -o gitleaks.tgz "https://example.test/gitleaks_${GITLEAKS_VERSION}.tgz"
                      echo "${GITLEAKS_SHA256}  gitleaks.tgz" | sha256sum -c -
                      tar -xzf gitleaks.tgz gitleaks
                  - name: Run one part
                    timeout-minutes: 10
                    run: bash scripts/checks.sh --part ${{ matrix.part }}

              checks-result:
                if: always() && github.event_name {is} 'pull_request'
                needs: [checks]
                runs-on: ubuntu-24.04
                timeout-minutes: 5
                steps:
                  - name: Every part passed
                    timeout-minutes: 2
                    env:
                      RESULT: ${{ needs.checks.result }}
                    run: test "$RESULT" = success

              checks-main:
                if: github.event_name {is} 'push'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                strategy:
                  fail-fast: false
                  matrix:
                    part: [gate, static, guards, repo]
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v4.2.2
                    timeout-minutes: 5
                    with:
                      fetch-depth: 0
                  - name: Install from the lock file
                    timeout-minutes: 5
                    run: composer install
                  - name: Run one part
                    timeout-minutes: 10
                    run: bash scripts/checks.sh --main --part ${{ matrix.part }}

              backend-tests:
                if: github.event_name {isnot} 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                env:
                  CHECK_BASE: ${{ github.base_ref }}
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    ports:
                      - 5432:5432
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v4.2.2
                    timeout-minutes: 5
                    with:
                      fetch-depth: 0
                  - name: Install from the lock file
                    timeout-minutes: 5
                    run: composer install
                  - name: Tests
                    timeout-minutes: 10
                    run: composer test:unit
                  - name: Mutation
                    if: github.event_name {is} 'pull_request'
                    timeout-minutes: 10
                    run: bash scripts/mutation.sh --changed

              smoke:
                if: github.event_name {isnot} 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                env:
                  CACHE_STORE: database
                  QUEUE_CONNECTION: database
                  DB_CONNECTION: pgsql
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    ports:
                      - 5432:5432
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v4.2.2
                    timeout-minutes: 5
                    with:
                      fetch-depth: 0
                  - name: Install from the lock file
                    timeout-minutes: 5
                    run: composer install --no-dev
                  - name: Boot
                    timeout-minutes: 5
                    run: php artisan migrate --force --seed

              scheduled:
                if: github.event_name {is} 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                permissions:
                  contents: read
                  actions: read
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v4.2.2
                    timeout-minutes: 5
                    with:
                      fetch-depth: 0
                  - name: Install from the lock file
                    timeout-minutes: 5
                    run: composer install
                  - name: Dashboard
                    timeout-minutes: 10
                    run: bash scripts/checks.sh --scheduled
                  - name: Mutation
                    timeout-minutes: 10
                    run: bash scripts/mutation.sh
            YAML . "\n");
    }

    public static function deploy(): string
    {
        return self::expand(<<<'YAML'
            name: Deploy
            on:
              workflow_run:
                workflows: [Kit checks]
                types: [completed]
            permissions:
              contents: read
            jobs:
              deploy:
                if: github.event.workflow_run.conclusion {is} 'success'
                runs-on: ubuntu-24.04
                timeout-minutes: 10
                steps:
                  - name: Deploy
                    timeout-minutes: 5
                    run: echo deploy
            YAML . "\n");
    }

    public static function canary(): string
    {
        return <<<'YAML'
            name: Canary
            on:
              schedule:
                - cron: '43 2 * * *'
            permissions:
              contents: read
            jobs:
              canary:
                runs-on: ubuntu-24.04
                timeout-minutes: 30
                steps:
                  - name: Static checks with the newest scripts
                    timeout-minutes: 10
                    run: bash scripts/checks.sh --part static
                  - name: Gate with the newest scripts
                    timeout-minutes: 10
                    run: bash scripts/checks.sh --part gate
            YAML . "\n";
    }

    public static function expand(string $text): string
    {
        return strtr($text, ['{is}' => '=' . '=', '{isnot}' => '!' . '=']);
    }

    public static function checksScript(): string
    {
        return <<<'SH_WRAP'
        set -euo pipefail
        bash "$GUARD/ci-ratchets.sh" --guard scripts/ci-ratchets.sh
        bash scripts/ci-ratchets.sh
        bash scripts/debt-dashboard.sh $DASHBOARD_MODE --offline --details
        TASKS=(
            "Format::vendor/bin/pint --test"
            "Refactor::vendor/bin/rector process --dry-run --no-progress-bar"
            "Analyser::vendor/bin/phpstan analyse --no-progress"
            "Type coverage::vendor/bin/pest --type-coverage --min=100"
            "Lint::vendor/bin/parallel-lint app tests"
            "Guard tests::vendor/bin/pest --group=arch"
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
