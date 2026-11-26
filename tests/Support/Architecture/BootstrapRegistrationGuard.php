<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Tests\Support\Globs;

final readonly class BootstrapRegistrationGuard
{
    public function __construct(
        private string $root,
        private string $providers,
        private string $bootstrap,
        private string $listeners,
        private string $discovery,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach (Globs::of($this->root . '/' . $this->providers . '/*.php') as $file) {
            foreach ($this->providerInSource((string) file_get_contents($file)) as $message) {
                $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ': ' . $message;
            }
        }

        $file = $this->root . '/' . $this->bootstrap;

        if (! is_file($file)) {
            return [...$violations, $this->bootstrap . ': the bootstrap file does not exist: the guard has read nothing'];
        }

        foreach ($this->bootstrapInSource((string) file_get_contents($file)) as $message) {
            $violations[] = $this->bootstrap . ': ' . $message;
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function providerInSource(string $source): array
    {
        if (preg_match('/^use ' . $this->listeners . '/m', $source) !== 1) {
            return [];
        }

        return ['a provider imports a listener of a module: listeners are found by discovery, so a new listener needs no line in a provider'];
    }

    /**
     * @return list<string>
     */
    public function bootstrapInSource(string $source): array
    {
        $messages = [];

        if (! str_contains($source, $this->discovery)) {
            $messages[] = 'the discovery of listeners is gone: add ' . $this->discovery . ', or a listener is never registered';
        }

        return $messages;
    }
}
