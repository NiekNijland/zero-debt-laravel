<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

final readonly class EmptyCatchGuard
{
    private const string MESSAGE = 'a broad catch that only swallows the exception: report it with report(), rethrow it, or catch the exact exception the code expects';

    /**
     * @param list<string> $directories
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $directories,
    ) {
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        return TemporarySource::with($source, fn (string $file): array => array_map(static fn (int $line): string => 'line ' . $line . ': ' . self::MESSAGE, $this->inspector->swallowedBroadCatchLines($file)));
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->directories as $directory) {
            foreach ($this->inspector->phpFiles($this->root . '/' . $directory) as $file) {
                foreach ($this->inspector->swallowedBroadCatchLines($file) as $line) {
                    $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ':' . $line . ': ' . self::MESSAGE;
                }
            }
        }

        sort($violations);

        return $violations;
    }
}
