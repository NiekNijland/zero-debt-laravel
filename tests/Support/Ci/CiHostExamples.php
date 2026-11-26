<?php

declare(strict_types=1);

namespace Tests\Support\Ci;

final class CiHostExamples
{
    public const string CHECKOUT_PIN = 'actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1';

    public static function githubWorkflow(): string
    {
        return CiExamples::expand(<<<'YAML'
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

            defaults:
              run:
                shell: bash

            jobs:
              checks:
                if: github.event_name {is} 'pull_request'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                container:
                  image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000
                strategy:
                  fail-fast: false
                  matrix:
                    part: [gate, static, guards, repo]
                env:
                  CHECK_BASE: ${{ github.base_ref }}
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - run: composer install
                  - name: Install gitleaks
                    if: matrix.part {is} 'repo'
                    env:
                      GITLEAKS_VERSION: 8.30.1
                      GITLEAKS_SHA256: 0000000000000000000000000000000000000000000000000000000000000000
                    run: |
                      curl -sSfL -o gitleaks.tgz "https://github.com/gitleaks/gitleaks/releases/download/v${GITLEAKS_VERSION}/gitleaks_${GITLEAKS_VERSION}_linux_x64.tar.gz"
                      echo "${GITLEAKS_SHA256}  gitleaks.tgz" | sha256sum -c -
                      tar -xzf gitleaks.tgz gitleaks
                      install -m 0755 gitleaks /usr/local/bin/gitleaks
                      rm gitleaks.tgz gitleaks
                  - run: bash scripts/checks.sh --part ${{ matrix.part }}

              checks-result:
                if: always() && github.event_name {is} 'pull_request'
                needs: [checks]
                runs-on: ubuntu-24.04
                timeout-minutes: 5
                steps:
                  - name: Every part of the checks run passed
                    env:
                      RESULT: ${{ needs.checks.result }}
                    run: test "$RESULT" = success

              checks-main:
                if: github.event_name {is} 'push'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                container:
                  image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000
                strategy:
                  fail-fast: false
                  matrix:
                    part: [gate, static, guards, repo]
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - run: composer install
                  - run: bash scripts/checks.sh --main --part ${{ matrix.part }}

              backend-tests:
                if: github.event_name {isnot} 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                container:
                  image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000
                env:
                  CHECK_BASE: ${{ github.base_ref }}
                  DB_HOST: database
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - run: composer install
                  - run: composer test:unit
                  - run: bash scripts/checks.sh --coverage coverage/clover.xml

              frontend-tests:
                if: github.event_name {isnot} 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                container:
                  image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - run: composer install
                  - run: npm test

              smoke:
                if: github.event_name {isnot} 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                container:
                  image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000
                env:
                  DB_HOST: database
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - run: composer install
                  - run: php artisan serve --env=ci &
                  - run: npx playwright test

              scheduled:
                if: github.event_name {is} 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                container:
                  image: php:8.4@sha256:0000000000000000000000000000000000000000000000000000000000000000
                permissions:
                  contents: read
                  actions: read
                env:
                  GH_TOKEN: ${{ github.token }}
                  GH_REPO: ${{ github.repository }}
                  DB_HOST: database
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - run: composer install
                  - name: Install gh
                    env:
                      GH_VERSION: 2.106.0
                      GH_SHA256: 0000000000000000000000000000000000000000000000000000000000000000
                    run: |
                      command -v gh > /dev/null && exit 0
                      curl -sSfL -o gh.tgz "https://github.com/cli/cli/releases/download/v${GH_VERSION}/gh_${GH_VERSION}_linux_amd64.tar.gz"
                      echo "${GH_SHA256}  gh.tgz" | sha256sum -c -
                      tar -xzf gh.tgz --strip-components=2 "gh_${GH_VERSION}_linux_amd64/bin/gh"
                      install -m 0755 gh /usr/local/bin/gh
                      rm gh.tgz gh
                  - run: bash scripts/checks.sh --scheduled
                  - run: bash scripts/mutation.sh
            YAML . "\n");
    }
}
