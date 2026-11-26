<?php

declare(strict_types=1);

namespace Tests\Support\Ci;

final class CiRunnerExamples
{
    public static function workflow(): string
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
                  - uses: shivammathur/setup-php@bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb # v2
                    with:
                      php-version: '8.4'
                      extensions: mbstring, pdo_pgsql
                      tools: composer:v2
                  - uses: actions/setup-node@cccccccccccccccccccccccccccccccccccccccc # v7.1.0
                    with:
                      node-version-file: .nvmrc
                  - run: composer install
                  - run: npm ci
                  - name: Install gitleaks
                    if: matrix.part {is} 'repo'
                    env:
                      GITLEAKS_VERSION: 8.30.1
                      GITLEAKS_SHA256: 0000000000000000000000000000000000000000000000000000000000000000
                    run: |
                      curl -sSfL -o gitleaks.tgz "https://github.com/gitleaks/gitleaks/releases/download/v${GITLEAKS_VERSION}/gitleaks_${GITLEAKS_VERSION}_linux_x64.tar.gz"
                      echo "${GITLEAKS_SHA256}  gitleaks.tgz" | sha256sum -c -
                      tar -xzf gitleaks.tgz gitleaks
                      sudo install -m 0755 gitleaks /usr/local/bin/gitleaks
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
                strategy:
                  fail-fast: false
                  matrix:
                    part: [gate, static, guards, repo]
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - uses: shivammathur/setup-php@bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb # v2
                    with:
                      php-version: '8.4'
                      extensions: mbstring, pdo_pgsql
                      tools: composer:v2
                  - uses: actions/setup-node@cccccccccccccccccccccccccccccccccccccccc # v7.1.0
                    with:
                      node-version-file: .nvmrc
                  - run: composer install
                  - run: npm ci
                  - run: bash scripts/checks.sh --main --part ${{ matrix.part }}

              backend-tests:
                if: github.event_name {isnot} 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                env:
                  CHECK_BASE: ${{ github.base_ref }}
                  DB_HOST: 127.0.0.1
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    ports:
                      - '5432:5432'
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - uses: shivammathur/setup-php@bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb # v2
                    with:
                      php-version: '8.4'
                      extensions: mbstring, pdo_pgsql
                      tools: composer:v2
                      coverage: pcov
                  - run: composer install
                  - run: composer test:unit
                  - run: bash scripts/checks.sh --coverage coverage/clover.xml

              frontend-tests:
                if: github.event_name {isnot} 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - uses: actions/setup-node@cccccccccccccccccccccccccccccccccccccccc # v7.1.0
                    with:
                      node-version-file: .nvmrc
                  - run: npm ci
                  - run: npm test

              smoke:
                if: github.event_name {isnot} 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                env:
                  DB_HOST: 127.0.0.1
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    ports:
                      - '5432:5432'
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - uses: shivammathur/setup-php@bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb # v2
                    with:
                      php-version: '8.4'
                      extensions: mbstring, pdo_pgsql
                      tools: composer:v2
                  - uses: actions/setup-node@cccccccccccccccccccccccccccccccccccccccc # v7.1.0
                    with:
                      node-version-file: .nvmrc
                  - run: composer install
                  - run: npm ci
                  - run: php artisan serve --env=ci &
                  - run: npx playwright test

              scheduled:
                if: github.event_name {is} 'schedule'
                runs-on: ubuntu-24.04
                timeout-minutes: 20
                permissions:
                  contents: read
                  actions: read
                env:
                  GH_TOKEN: ${{ github.token }}
                  GH_REPO: ${{ github.repository }}
                  DB_HOST: 127.0.0.1
                services:
                  database:
                    image: postgres:17
                    env:
                      POSTGRES_PASSWORD: ci
                    ports:
                      - '5432:5432'
                    options: >-
                      --health-cmd "pg_isready -U postgres"
                      --health-interval 5s --health-timeout 5s --health-retries 12
                steps:
                  - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1
                    with:
                      fetch-depth: 0
                  - uses: shivammathur/setup-php@bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb # v2
                    with:
                      php-version: '8.4'
                      extensions: mbstring, pdo_pgsql
                      tools: composer:v2
                      coverage: pcov
                  - uses: actions/setup-node@cccccccccccccccccccccccccccccccccccccccc # v7.1.0
                    with:
                      node-version-file: .nvmrc
                  - run: composer install
                  - run: npm ci
                  - run: bash scripts/checks.sh --scheduled
                  - run: bash scripts/mutation.sh
            YAML . "\n");
    }
}
