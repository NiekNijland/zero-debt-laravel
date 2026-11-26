<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use InvalidArgumentException;

final readonly class TestAssertionGuard
{
    private const array ASSERTING_FUNCTIONS = ['arch'];

    private const array ASSERTING_METHODS = ['tothrow', 'throws', 'throwsif', 'throwsunless', 'throwsnoexceptions'];

    private const array MOCKERY_EXPECTATIONS = ['shouldhavereceived', 'shouldnothavereceived', 'shouldnotreceive', 'expects'];

    private const array MOCKERY_COUNTS = ['once', 'twice', 'never', 'times', 'atleast', 'atmost'];

    /**
     * @param list<string> $folders
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $folders,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];
        $present = array_filter($this->folders, fn (string $folder): bool => is_dir($this->root . '/' . $folder));

        if ($present === []) {
            throw new InvalidArgumentException('None of the folders ' . implode(', ', $this->folders) . ' exists: a guard that reads no test file passes without checking');
        }

        foreach ($present as $folder) {
            foreach ($this->inspector->phpFiles($this->root . '/' . $folder) as $file) {
                if (str_ends_with($file, 'Test.php') && ! $this->asserts((string) file_get_contents($file))) {
                    $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ' asserts nothing: add an expect() on the outcome, or ->throwsNoExceptions() when there is truly nothing else to check';
                }
            }
        }

        return $violations;
    }

    public function asserts(string $source): bool
    {
        $calls = $this->inspector->calls($source);
        $names = array_column($calls, 'name');
        $matches = array_any($calls, static fn (array $call): bool => $call['kind'] !== 'function' && str_starts_with($call['name'], 'to'));
        $doubles = in_array('shouldreceive', $names, true) || in_array('expects', $names, true);

        foreach ($calls as $call) {
            $named = $call['kind'] === 'function'
                ? in_array($call['name'], self::ASSERTING_FUNCTIONS, true) || ($call['name'] === 'expect' && $matches)
                : in_array($call['name'], [...self::ASSERTING_METHODS, ...self::MOCKERY_EXPECTATIONS], true)
                    || ($doubles && in_array($call['name'], self::MOCKERY_COUNTS, true));

            if ($named || str_starts_with($call['name'], 'assert')) {
                return true;
            }
        }

        return false;
    }
}
