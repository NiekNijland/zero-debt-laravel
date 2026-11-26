<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Error;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

final readonly class MethodSizeGuard
{
    private const string SENTENCE = 'a method at most N lines, a controller action at most N';

    /** @var array{int, int}|null */
    private ?array $limits;

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
        private string $guide,
        private string $controllerFolder,
    ) {
        $path = $this->root . '/' . $this->guide;
        $text = is_file($path) ? (string) file_get_contents($path) : '';
        $this->limits = preg_match('/a method at most (\d+) lines, a controller action at most (\d+)\./', $text, $match) === 1 ? [(int) $match[1], (int) $match[2]] : null;
    }

    /**
     * @return array{int, int}|null
     */
    public function limits(): ?array
    {
        return $this->limits;
    }

    /**
     * @return list<string>
     */
    public function findings(): array
    {
        if ($this->limits === null) {
            return ["{$this->guide} no longer holds the sentence the guard reads (" . self::SENTENCE . '): restore it, the guard takes its numbers from it'];
        }

        $findings = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            $path = mb_substr($file, mb_strlen($this->root) + 1);

            try {
                $statements = PhpSource::statements($file);
            } catch (Error $error) {
                $findings[] = "unreadable {$path}: {$error->getMessage()}";

                continue;
            }

            foreach (new NodeFinder()->findInstanceOf($statements, ClassLike::class) as $class) {
                foreach ($class->getMethods() as $method) {
                    $lines = $method->getEndLine() - $method->name->getStartLine() + 1;

                    if ($lines > $this->limitOf($path, $method, $this->limits)) {
                        $findings[] = "{$path}::" . ($class->name?->toString() ?? 'class@anonymous') . "::{$method->name->toString()}::{$lines}";
                    }
                }
            }
        }

        sort($findings, SORT_STRING);

        return $findings;
    }

    /**
     * @param list<string> $findings
     * @return list<string>
     */
    public function explain(array $findings): array
    {
        return array_map(function (string $finding): string {
            $parts = explode('::', $finding);

            if (count($parts) !== 4 || $this->limits === null) {
                return $finding;
            }

            return "{$parts[0]}: {$parts[1]}::{$parts[2]} has {$parts[3]} lines, over the limit of {$this->limits[0]} for a method and {$this->limits[1]} for a controller action: split it by responsibility, never by line count, as {$this->guide} says.";
        }, $findings);
    }

    /**
     * @param array{int, int} $limits
     */
    private function limitOf(string $path, ClassMethod $method, array $limits): int
    {
        $action = in_array($this->controllerFolder, explode('/', dirname($path)), true) && $method->isPublic() && $method->name->toString() !== '__construct';

        return $action ? $limits[1] : $limits[0];
    }
}
