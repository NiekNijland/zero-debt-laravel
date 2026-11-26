<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class ComposerScriptsGuard
{
    public const array RULES = ['composer-scripts'];

    private const array EVENT_KEYS = ['pre-install-cmd', 'post-install-cmd', 'pre-update-cmd', 'post-update-cmd'];

    /**
     * @param list<string> $requiredScripts the names the task runner keeps
     * @param list<string> $wiredScripts the scripts whose every command is a line of the checks script
     * @param string $coverageScript the script that runs the tests with coverage
     * @param list<string> $botKeys the keys the dependency bot's config holds
     * @param list<string> $botExtends the presets the bot's config extends
     */
    public function __construct(
        private array $requiredScripts,
        private string $testScript,
        private string $checksCommand,
        private array $wiredScripts,
        private string $unwiredScript,
        private string $coverageScript,
        private array $botKeys,
        private array $botExtends,
    ) {
    }

    /**
     * @param array<string, mixed> $composer
     * @param array<string, mixed> $bot
     * @param list<string> $workflows the texts of every workflow file
     * @return list<string>
     */
    public function inProject(array $composer, string $checksScript, array $bot, array $workflows): array
    {
        $scripts = YamlNode::map($composer['scripts'] ?? null);
        $violations = [];

        foreach ($this->requiredScripts as $name) {
            if (! array_key_exists($name, $scripts)) {
                $violations[] = $this->fail('the task runner has no script `' . $name . '`: the names are the same in every project');
            }
        }

        if ($this->commands($scripts[$this->testScript] ?? null) !== [$this->checksCommand]) {
            $violations[] = $this->fail('the script `' . $this->testScript . '` is not exactly `' . $this->checksCommand . '`: one command runs what CI runs');
        }

        if (! array_any(self::commands($scripts[$this->coverageScript] ?? null), fn (string $command): bool => str_starts_with($command, $this->checksCommand . ' --coverage'))) {
            $violations[] = self::fail('the script `' . $this->coverageScript . '` does not hold the coverage at the committed floor: it ends in `' . $this->checksCommand . ' --coverage <report>`');
        }

        foreach ($this->wiredScripts as $name) {
            foreach ($this->commands($scripts[$name] ?? null) as $command) {
                if (! str_contains($checksScript, $command)) {
                    $violations[] = $this->fail('the script `' . $name . '` runs `' . $command . '`, which is no line of the checks script: the developer and CI run different checks');
                }
            }
        }

        if (array_any($workflows, fn (string $workflow): bool => str_contains($workflow, 'composer ' . $this->unwiredScript))) {
            $violations[] = $this->fail('a workflow calls `composer ' . $this->unwiredScript . '`: it writes files, and CI only checks');
        }

        foreach (self::EVENT_KEYS as $event) {
            if (array_any($this->commands($scripts[$event] ?? null), static fn (string $command): bool => preg_match('/\b(update|bump)\b/', $command) === 1)) {
                $violations[] = $this->fail('the event `' . $event . '` runs an update or a bump: a dependency changes only in a pull request of its own');
            }
        }

        return [...$violations, ...$this->botViolations($bot)];
    }

    /**
     * @return list<string>
     */
    private function commands(mixed $script): array
    {
        $commands = is_string($script) ? [$script] : YamlNode::texts($script);

        return array_values(array_filter($commands, static fn (string $command): bool => ! str_starts_with($command, 'Composer\\Config::')));
    }

    private function fail(string $message): string
    {
        return '[composer-scripts] ' . $message;
    }

    /**
     * @param array<string, mixed> $bot
     * @return list<string>
     */
    private function botViolations(array $bot): array
    {
        $violations = [];

        foreach ($this->botKeys as $key) {
            if (! isset($bot[$key])) {
                $violations[] = $this->fail("the dependency bot's config has no `" . $key . "`: it keeps the keys of the kit's");
            }
        }

        foreach ($this->botExtends as $preset) {
            if (! in_array($preset, YamlNode::texts($bot['extends'] ?? null), true)) {
                $violations[] = $this->fail("the dependency bot's config does not extend `" . $preset . '`');
            }
        }

        return $violations;
    }
}
