<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use InvalidArgumentException;
use Tests\Support\Globs;

final readonly class TestHelperMirrorGuard
{
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $supportFolder,
        private string $testFolder,
        private string $namespace,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        if (! is_dir($this->root . '/' . $this->supportFolder)) {
            throw new InvalidArgumentException('The directory ' . $this->supportFolder . ' does not exist: a guard over a missing folder would pass without reading a file');
        }

        foreach (Globs::of($this->root . '/' . $this->supportFolder . '/*', GLOB_ONLYDIR) as $tree) {
            if (! is_dir($this->root . '/' . $this->testFolder . '/' . basename($tree))) {
                continue;
            }

            foreach ($this->inspector->phpFiles($tree) as $file) {
                $violation = $this->violationOf(mb_substr($file, mb_strlen($this->root . '/' . $this->supportFolder . '/')));

                if ($violation !== null) {
                    $violations[] = $this->supportFolder . '/' . mb_substr($file, mb_strlen($this->root . '/' . $this->supportFolder . '/')) . ': ' . $violation;
                }
            }
        }

        return $violations;
    }

    private function violationOf(string $path): ?string
    {
        $class = $this->namespace . '\\' . str_replace('/', '\\', mb_substr($path, 0, -4));

        if (preg_match('/^(.+)(Helpers|Scenario)\.php$/', $path, $parts) !== 1) {
            return null;
        }

        if (! array_key_exists($class, $this->inspector->declaredTypes($this->root . '/' . $this->supportFolder . '/' . $path))) {
            return 'the file does not declare ' . $class . ': the class and its namespace follow the path';
        }

        if (is_file($this->root . '/' . $this->testFolder . '/' . $parts[1] . 'Test.php')) {
            return null;
        }

        if ($parts[2] === 'Scenario' && $this->returnedByNeighbor($path, $class)) {
            return null;
        }

        return 'there is no ' . $this->testFolder . '/' . $parts[1] . 'Test.php: the helpers of one test mirror its path and carry its name ('
            . basename($parts[1]) . 'Test.php with ' . basename($parts[1]) . 'Helpers.php), a scenario sits next to the helpers that build it, and a class that several tests share is a themed class directly in ' . $this->supportFolder;
    }

    private function returnedByNeighbor(string $path, string $class): bool
    {
        foreach (Globs::of($this->root . '/' . $this->supportFolder . '/' . dirname($path) . '/*Helpers.php') as $neighbor) {
            $mirrored = $this->root . '/' . $this->testFolder . '/' . dirname($path) . '/' . basename($neighbor, 'Helpers.php') . 'Test.php';

            if (is_file($mirrored) && in_array($class, $this->inspector->referencedNames($neighbor), true)) {
                return true;
            }
        }

        return false;
    }
}
