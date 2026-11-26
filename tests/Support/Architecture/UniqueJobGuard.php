<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;

final readonly class UniqueJobGuard
{
    private const array UNIQUE_CONTRACTS = [ShouldBeUnique::class, ShouldBeUniqueUntilProcessing::class];

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $sources = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            $sources[mb_substr($file, mb_strlen($this->root) + 1)] = (string) file_get_contents($file);
        }

        return $this->check($sources);
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        return $this->check(['' => $source]);
    }

    /**
     * @param array<string, string> $sources
     * @return list<string>
     */
    private function check(array $sources): array
    {
        $graph = ClassGraph::of($sources);
        $violations = [];

        foreach ($graph->concreteClasses() as $class) {
            $contract = $graph->reaches($class, self::UNIQUE_CONTRACTS);

            if ($contract === null || $graph->hasMethod($class, 'uniqueId') || $graph->property($class, 'uniqueId') === 'instance') {
                continue;
            }

            $violations[] = ($class['path'] === '' ? '' : $class['path'] . ': ') . $class['name'] . ' implements ' . basename(str_replace('\\', '/', $contract))
                . ($graph->property($class, 'uniqueId') === 'default'
                    ? ' with a $uniqueId property that has a default, so the lock is per class: set it per instance (promote it in the constructor) or declare public function uniqueId(): string'
                    : ' without uniqueId(): declare public function uniqueId(): string and return the key of the record the job works on, so the lock is per record and not per class');
        }

        return $violations;
    }
}
