<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use InvalidArgumentException;

final readonly class TestCaseFixtureGuard
{
    /**
     * @param list<string> $testCaseFiles
     */
    public function __construct(
        private string $root,
        private array $testCaseFiles,
        private string $analyserConfig,
    ) {
    }

    /**
     * @return list<string>
     */
    public function fixtureProperties(string $source): array
    {
        preg_match_all('/^\s+public\s+(?!function|static function|const)[^(=;]*(\$\w+)/m', $source, $found);

        return array_map(static fn (string $name): string => 'a public property ' . $name . ' on a base test case is a fixture shared by every test: build the state in the test, in a local variable', $found[1]);
    }

    /**
     * @return list<string>
     */
    public function cratesIn(string $config): array
    {
        preg_match_all('/^\s*-\s+(Tests\\\\\w*TestCase)\s*$/m', $config, $found);

        return array_map(static fn (string $class): string => $class . ' is listed as a universal object crate: the analyser then stops seeing the properties a test reads, remove the line', $found[1]);
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->testCaseFiles as $file) {
            foreach ($this->fixtureProperties($this->read($file)) as $message) {
                $violations[] = $file . ': ' . $message;
            }
        }

        foreach ($this->cratesIn($this->read($this->analyserConfig)) as $message) {
            $violations[] = $this->analyserConfig . ': ' . $message;
        }

        return $violations;
    }

    private function read(string $file): string
    {
        throw_unless(is_file($this->root . '/' . $file), InvalidArgumentException::class, 'The file ' . $file . ' does not exist under the root: a guard over a missing file would pass without reading it');

        return (string) file_get_contents($this->root . '/' . $file);
    }
}
