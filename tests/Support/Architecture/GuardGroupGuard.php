<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class GuardGroupGuard
{
    public const array RULES = ['guard-group'];

    /**
     * @param string $guardTestSuffix the end of the name of a guard test file
     * @param string $groupMarker the text a guard test holds when it is in the guard group
     * @param string $groupCommand the command of the checks script that runs the guard group
     * @param list<string> $keptByTheOwner guard tests that carry no group on purpose, by file name
     */
    public function __construct(
        private string $testFolder,
        private string $guardTestSuffix,
        private string $groupMarker,
        private string $groupCommand,
        private array $keptByTheOwner = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(string $checksScript): array
    {
        $files = glob($this->testFolder . '/*' . $this->guardTestSuffix);
        $sources = [];

        foreach ($files === false ? [] : $files as $file) {
            $sources[basename($file)] = (string) file_get_contents($file);
        }

        return $this->inTests($sources, $checksScript);
    }

    /**
     * @param array<string, string> $sources the guard test files by name
     * @return list<string>
     */
    public function inTests(array $sources, string $checksScript): array
    {
        $violations = [];

        foreach ($sources as $name => $source) {
            if (! in_array($name, $this->keptByTheOwner, true) && ! str_contains($source, $this->groupMarker)) {
                $violations[] = '[guard-group] ' . $name . ' is not in the guard group: add `' . $this->groupMarker . '`, or a guard test runs in no step of the checks';
            }
        }

        if (! str_contains($checksScript, $this->groupCommand)) {
            $violations[] = '[guard-group] the checks script does not run the guard group (`' . $this->groupCommand . '`)';
        }

        return $violations;
    }
}
