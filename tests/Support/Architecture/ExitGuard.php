<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;

final readonly class ExitGuard
{
    /**
     * @param list<string> $trees
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $trees,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->trees as $tree) {
            foreach ($this->inspector->phpFiles($this->root . '/' . $tree, mayBeAbsent: true) as $file) {
                foreach ($this->inSource((string) file_get_contents($file)) as $violation) {
                    $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ':' . $violation;
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
        $violations = [];

        $exits = new NodeFinder()->find(PhpSource::parse($source), static fn (Node $node): bool => $node instanceof Exit_
            || ($node instanceof FuncCall && $node->name instanceof Name && in_array($node->name->toLowerString(), ['exit', 'die'], true)));

        foreach ($exits as $exit) {
            $construct = match (true) {
                $exit instanceof Exit_ => $exit->getAttribute('kind') === Exit_::KIND_DIE ? 'die' : 'exit',
                $exit instanceof FuncCall && $exit->name instanceof Name => $exit->name->toLowerString(),
                default => 'exit',
            };

            $violations[] = $exit->getStartLine() . ': ' . $construct
                . ' ends the process and skips terminating middleware, queued events and the test runner: return from the code, throw an exception, or return an exit code from the command (return self::FAILURE)';
        }

        return $violations;
    }
}
