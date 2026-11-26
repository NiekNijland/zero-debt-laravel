<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class GuardShapeGuard
{
    private const string PATH_MESSAGE = 'a detector finds files by reflection or a directory scan, takes the root and every folder as constructor arguments, and reads a table of folders from the guide that holds it: pass the path in from the thin test';

    /**
     * @param list<string> $testFolders
     * @param list<string> $groups
     * @param list<string> $groupedFolders
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $guardFolder,
        private array $testFolders,
        private array $groups,
        private array $groupedFolders,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $guards = [];
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->guardFolder) as $file) {
            $violations = [...$violations, ...$this->detectorViolations($file)];

            foreach (array_keys($this->inspector->declaredTypes($file)) as $type) {
                if (str_ends_with($type, 'Guard')) {
                    $guards[$type] = $this->relative($file);
                }
            }
        }

        $tests = $this->guardTests($guards);

        foreach ($guards as $type => $path) {
            if (! array_any($tests, static fn (array $named): bool => in_array($type, $named, true))) {
                $violations[] = $path . ': ' . class_basename($type) . ' is named by no guard test: a guard has a thin test with failing examples that carry the words of the message, passing examples with the near misses, and one case over the real tree';
            }
        }

        foreach (array_keys($tests) as $test) {
            $violations = [...$violations, ...$this->testViolations($test)];
        }

        sort($violations);

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function detectorViolations(string $file): array
    {
        $violations = [];
        $path = $this->relative($file);

        foreach (array_keys($this->inspector->declaredTypes($file)) as $type) {
            if (str_ends_with($type, 'Helpers') || str_ends_with($type, 'Scenario')) {
                $violations[] = $path . ': ' . class_basename($type) . ' ends in Helpers or Scenario, which names the helpers of one test: a detector is a <Subject>Guard class';
            }
        }

        foreach (array_unique($this->inspector->stringLiterals($file)) as $literal) {
            if ($this->namesPath($literal)) {
                $violations[] = $path . ': the literal "' . $literal . '" names a path of the repository: ' . self::PATH_MESSAGE;
            }
        }

        return $violations;
    }

    /**
     * @param array<string, string> $guards
     * @return array<string, list<string>> the guard classes each test file of the test folders names, for the files that name any
     */
    private function guardTests(array $guards): array
    {
        $tests = [];

        foreach ($this->testFolders as $folder) {
            foreach ($this->inspector->phpFiles($this->root . '/' . $folder) as $file) {
                $named = array_values(array_intersect($this->inspector->referencedNames($file), array_keys($guards)));

                if ($named !== []) {
                    $tests[$this->relative($file)] = $named;
                }
            }
        }

        return $tests;
    }

    /**
     * @return list<string>
     */
    private function testViolations(string $path): array
    {
        $parts = PestExampleReader::parts($this->root . '/' . $path, $this->groups);
        $missing = [
            'failing' => 'failing examples: a dataset whose test asserts the words of the message (toContain, toStartWith)',
            'passing' => 'passing examples: a test that asserts an empty result, with the near misses',
            'realTree' => 'real-tree case: a test without a dataset that runs the guard over dirname(__DIR__, n) and asserts an empty result',
        ];
        $violations = [];

        foreach ($missing as $part => $words) {
            if (! $parts[$part]) {
                $violations[] = $path . ': the guard test has no ' . $words;
            }
        }

        if (! $parts['group'] && array_any($this->groupedFolders, static fn (string $folder): bool => str_starts_with($path, $folder . '/'))) {
            $violations[] = $path . ': the guard test is not in the group ' . implode(' or ', $this->groups) . ', so the guard part of the checks does not run it';
        }

        return $violations;
    }

    private function namesPath(string $literal): bool
    {
        $trimmed = mb_trim($literal, '/');

        if ($trimmed === '' || in_array($trimmed, ['.', '..'], true) || preg_match('/[\s*?{}()\[\]^$|\\\\]/', $trimmed) === 1) {
            return false;
        }

        $exists = is_file($this->root . '/' . $trimmed) || is_dir($this->root . '/' . $trimmed);

        return $exists && (str_contains($literal, '/') || pathinfo($trimmed, PATHINFO_EXTENSION) !== '');
    }

    private function relative(string $file): string
    {
        return mb_substr($file, mb_strlen($this->root) + 1);
    }
}
