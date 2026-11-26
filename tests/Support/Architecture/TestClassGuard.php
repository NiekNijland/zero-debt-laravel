<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class TestClassGuard
{
    private const string DECLARATION = '/^\h*(#\[[^\n]*\]\h*)*(\w+\h+)*(class|interface|trait|enum)\h+\w/mi';

    /**
     * @param list<string> $suites
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $suites,
    ) {
    }

    /**
     * @return array<string, list<string>>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->suites as $suite) {
            foreach ($this->inspector->phpFiles($this->root . '/' . $suite) as $file) {
                if (! str_ends_with($file, 'Test.php') || preg_match(self::DECLARATION, (string) file_get_contents($file)) !== 1) {
                    continue;
                }

                $foreign = array_values(array_filter(
                    array_keys($this->inspector->declaredTypes($file)),
                    static fn (string $type): bool => class_basename($type) !== basename($file, '.php'),
                ));

                if ($foreign !== []) {
                    $violations[mb_substr($file, mb_strlen($this->root) + 1)] = $foreign;
                }
            }
        }

        ksort($violations);

        return $violations;
    }
}
