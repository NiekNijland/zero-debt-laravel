<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final readonly class QueuedClassLimitsGuard
{
    private const array QUEUED = [ShouldQueue::class, ShouldQueueAfterCommit::class];

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
        private string $supervisorConfig,
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
            if ($graph->reaches($class, self::QUEUED) === null) {
                continue;
            }

            $prefix = ($class['path'] === '' ? '' : $class['path'] . ': ') . $class['name'];

            if ($graph->property($class, 'timeout') === null && ! $graph->hasAttribute($class, 'Timeout')) {
                $violations[] = $prefix . ' is queued without $timeout: declare public int $timeout = <seconds>, at most the timeout of the supervisor of its queue in ' . $this->supervisorConfig . ', so the limit of the work stands in the class';
            }

            if ($graph->property($class, 'tries') === null && ! $graph->hasMethod($class, 'retryUntil') && ! $graph->hasAttribute($class, 'Tries')) {
                $violations[] = $prefix . ' is queued without $tries: declare public int $tries = <attempts> (1 when a second run would repeat a side effect), or retryUntil() when the work retries until a moment';
            }
        }

        return $violations;
    }
}
