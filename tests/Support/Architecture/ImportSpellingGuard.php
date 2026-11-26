<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use RuntimeException;

use Tests\Support\Globs;

final readonly class ImportSpellingGuard
{
    public function __construct(
        private string $root,
        private string $controllers,
    ) {
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    public function controllerImports(array $files): array
    {
        $violations = [];

        foreach ($files as $path) {
            throw_unless(is_file($this->root . '/' . $path), RuntimeException::class, "the file {$path} does not exist under the root: a guard that reads a named file fails when it is renamed");

            foreach ($this->controllerImportInSource((string) file_get_contents($this->root . '/' . $path)) as $message) {
                $violations[] = $path . ': ' . $message;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function controllerImportInSource(string $source): array
    {
        $pattern = '/^use ' . preg_quote($this->controllers, '/') . '\\\\[\w\\\\]*Controller(?: as \w+)?;$/m';

        return preg_match($pattern, $source) === 1 ? ['a route file imports a controller: import the folder and write Folder\Noun\VerbController::class'] : [];
    }

    /**
     * @return list<string>
     */
    public function files(string $pattern): array
    {
        return array_map(fn (string $file): string => mb_substr($file, mb_strlen($this->root) + 1), Globs::of($this->root . '/' . $pattern));
    }
}
