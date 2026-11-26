<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Database\Eloquent\Model;

final readonly class ModelMagicStaticCallGuard
{
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

        foreach ($this->folders as $folder) {
            foreach ($this->inspector->phpFiles($this->root . '/' . $folder) as $file) {
                foreach ($this->inFile($file) as $message) {
                    $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ': ' . $message;
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        return TemporarySource::with($source, fn (string $file): array => $this->inFile($file));
    }

    /**
     * @return list<string>
     */
    private function inFile(string $file): array
    {
        $messages = [];

        foreach (PhpCallReader::staticCalls($file) as $call) {
            if (class_exists($call['class']) && is_subclass_of($call['class'], Model::class) && ! method_exists($call['class'], $call['method'])) {
                $messages[] = 'line ' . $call['line'] . ': ' . $call['method'] . '() is a magic static call on a model: start the query at ' . basename(str_replace('\\', '/', $call['class'])) . '::query()';
            }
        }

        return $messages;
    }
}
