<?php

declare(strict_types=1);

namespace Tests\Support\Ci;

use Tests\Support\Architecture\GithubWorkflowGuard;

final class CiHostVariants
{
    public const string RUNNER_TEST_SCRIPT = "php -d memory_limit=-1 vendor/bin/pest --coverage-clover=coverage/clover.xml\nbash scripts/checks.sh --coverage coverage/clover.xml";

    private const string POSTGRES_SERVICE = "        image: postgres:17\n        env:\n          POSTGRES_PASSWORD: ci\n        options: >-\n          --health-cmd \"pg_isready -U postgres\"\n";

    private const string RUNNER_SERVICES = "    services:\n      database:\n        image: postgres:17\n        env:\n          POSTGRES_PASSWORD: ci\n        ports:\n          - '5432:5432'\n        options: >-\n          --health-cmd \"pg_isready -U postgres\"\n          --health-interval 5s --health-timeout 5s --health-retries 12\n";

    private const string HEALTH = "          --health-interval 5s --health-timeout 5s --health-retries 12\n";

    /**
     * @param list<string> $hardened other workflow files the owner has the guard read
     * @param list<string> $auxiliary other workflow files that run the checks script on purpose
     * @param array<string, list<string>> $secrets the secret names declared per auxiliary workflow file
     */
    public static function githubGuard(string $databaseImage = 'postgres:17', bool $frontend = true, array $hardened = [], string $backendTestScript = '', array $auxiliary = [], array $secrets = []): GithubWorkflowGuard
    {
        return new GithubWorkflowGuard('', '', 'kit-checks.yml', ['main', 'develop'], 'composer test:unit', $databaseImage, $frontend, 'playwright', hardenedWorkflows: $hardened, backendTestScript: $backendTestScript, auxiliaryWorkflows: $auxiliary, auxiliarySecrets: $secrets);
    }

    /**
     * @param array<string, string>|null $files the workflow files by name; the kit's file alone when null
     */
    public static function githubViolations(string $workflow, ?GithubWorkflowGuard $guard = null, ?array $files = null): string
    {
        return implode("\n", ($guard ?? self::githubGuard())->inWorkflows($files ?? ['kit-checks.yml' => $workflow], CiExamples::checks()));
    }

    public static function inGithubJob(string $job, string $from, string $to): string
    {
        return CiExamples::changeAfter(CiHostExamples::githubWorkflow(), "\n  " . $job . ":\n", $from, $to);
    }

    public static function withoutGithubJob(string $job): string
    {
        $source = CiHostExamples::githubWorkflow();
        $at = mb_strpos($source, "\n  " . $job . ":\n");
        $next = preg_match('/\n  [a-z-]+:\n/', $source, $match, PREG_OFFSET_CAPTURE, ($at === false ? 0 : $at) + 3) === 1 ? $match[0][1] : mb_strlen($source);

        return $at === false ? $source : mb_substr($source, 0, $at) . mb_substr($source, $next);
    }

    public static function githubWorkflowFor(string $engine): string
    {
        $service = match ($engine) {
            'mysql' => "        image: mysql:8.4\n        env:\n          MYSQL_ROOT_PASSWORD: ci\n        options: >-\n          --health-cmd \"mysqladmin ping -h 127.0.0.1 -uroot -pci\"\n",
            'mariadb' => "        image: mariadb:11.4\n        env:\n          MARIADB_ROOT_PASSWORD: ci\n        options: >-\n          --health-cmd \"healthcheck.sh --connect --innodb_initialized\"\n",
            default => self::POSTGRES_SERVICE,
        };

        return str_replace(self::POSTGRES_SERVICE, $service, CiHostExamples::githubWorkflow());
    }

    public static function githubWorkflowWithoutDatabase(): string
    {
        $source = str_replace("    services:\n      database:\n" . self::POSTGRES_SERVICE . "          --health-interval 5s --health-timeout 5s --health-retries 12\n", '', CiHostExamples::githubWorkflow());

        return str_replace("      DB_HOST: database\n", '', str_replace("    env:\n      DB_HOST: database\n", '', $source));
    }

    /**
     * @param list<string> $auxiliaryImages
     */
    public static function githubRunnerGuard(string $databaseImage = 'postgres:17', array $auxiliaryImages = [], string $hostVariable = 'DB_HOST'): GithubWorkflowGuard
    {
        return new GithubWorkflowGuard('', '', 'kit-checks.yml', ['main', 'develop'], 'composer test:unit', $databaseImage, true, 'playwright', auxiliaryImages: $auxiliaryImages, hostVariable: $hostVariable);
    }

    public static function githubRunnerViolations(string $workflow, ?GithubWorkflowGuard $guard = null): string
    {
        return implode("\n", ($guard ?? self::githubRunnerGuard())->inWorkflows(['kit-checks.yml' => $workflow], CiExamples::checks()));
    }

    public static function inGithubRunnerJob(string $job, string $from, string $to): string
    {
        return CiExamples::changeAfter(CiRunnerExamples::workflow(), "\n  " . $job . ":\n", $from, $to);
    }

    public static function githubRunnerWorkflowFor(string $engine): string
    {
        $services = match ($engine) {
            'mysql' => "    services:\n      database:\n        image: mysql:8.4\n        env:\n          MYSQL_ROOT_PASSWORD: ci\n        ports:\n          - '3306:3306'\n        options: >-\n          --health-cmd \"mysqladmin ping -h 127.0.0.1 -uroot -pci\"\n" . self::HEALTH,
            'mariadb' => "    services:\n      database:\n        image: mariadb:11.4\n        env:\n          MARIADB_ROOT_PASSWORD: ci\n        ports:\n          - '3306:3306'\n        options: >-\n          --health-cmd \"healthcheck.sh --connect --innodb_initialized\"\n" . self::HEALTH,
            'mongo' => "    services:\n      database:\n        image: mongo:8\n        ports:\n          - '27017:27017'\n        options: >-\n          --health-cmd \"mongosh --quiet --eval 'db.runCommand({ping:1})'\"\n" . self::HEALTH . "      cache:\n        image: redis:7\n        ports:\n          - '6379:6379'\n        options: >-\n          --health-cmd \"redis-cli ping\"\n" . self::HEALTH,
            default => self::RUNNER_SERVICES,
        };
        $host = $engine === 'mongo' ? 'MONGODB_URI: mongodb://127.0.0.1:27017' : 'DB_HOST: 127.0.0.1';

        return str_replace('DB_HOST: 127.0.0.1', $host, str_replace(self::RUNNER_SERVICES, $services, CiRunnerExamples::workflow()));
    }

    public static function githubRunnerGuardFor(string $engine): GithubWorkflowGuard
    {
        return match ($engine) {
            'mysql' => self::githubRunnerGuard('mysql:8.4'),
            'mariadb' => self::githubRunnerGuard('mariadb:11.4'),
            'mongo' => self::githubRunnerGuard('mongo:8', ['redis:7'], 'MONGODB_URI'),
            default => self::githubRunnerGuard(),
        };
    }

    public static function githubWorkflowOnRunner(): string
    {
        $source = str_replace("      - run: bash scripts/checks.sh --coverage coverage/clover.xml\n", '', CiRunnerExamples::workflow());

        return CiExamples::changeAfter(
            CiExamples::changeAfter($source, "\n  smoke:\n", '      DB_HOST: 127.0.0.1', "      DB_HOST: 127.0.0.1\n      SMOKE_BASE_URL: http://127.0.0.1:8000"),
            "\n  smoke:\n",
            "      - run: npx playwright test\n",
            "      - run: curl -sS --retry 30 --retry-connrefused -o /dev/null \"\$SMOKE_BASE_URL/\"\n      - run: npx playwright test\n",
        );
    }

    public static function canaryWorkflow(): string
    {
        return "name: Canary\non:\n  schedule:\n    - cron: '41 4 * * *'\n  workflow_dispatch:\npermissions:\n  contents: read\njobs:\n  canary:\n    runs-on: ubuntu-24.04\n    timeout-minutes: 30\n    steps:\n      - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1\n        with:\n          fetch-depth: 0\n      - run: composer install\n      - run: bash scripts/checks.sh --main --part static\n";
    }

    public static function syncWorkflow(string $on = "  schedule:\n    - cron: '23 4 * * 1'\n  workflow_dispatch:\n", string $secrets = '${{ secrets.KIT_SYNC_TOKEN || github.token }}'): string
    {
        return "name: Sync\non:\n" . $on . "permissions:\n  contents: read\njobs:\n  sync:\n    runs-on: ubuntu-24.04\n    timeout-minutes: 20\n    steps:\n      - uses: actions/checkout@aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa # v7.0.1\n        timeout-minutes: 5\n        with:\n          fetch-depth: 0\n          token: " . $secrets . "\n";
    }

    public static function deployWorkflow(): string
    {
        return "name: Deploy\non:\n  push:\n    branches: [main]\njobs:\n  deploy:\n    runs-on: ubuntu-latest\n    steps:\n      - uses: actions/checkout@v4\n      - uses: appleboy/ssh-action@v1.0.3\n        with:\n          key: \${{ secrets.DEPLOY_KEY }}\n";
    }
}
