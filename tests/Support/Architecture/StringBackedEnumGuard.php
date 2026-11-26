<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node\Stmt\Enum_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final readonly class StringBackedEnumGuard
{
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
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $violation) {
                $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ': ' . $violation;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $statements = new NodeTraverser(new NameResolver())->traverse(new ParserFactory()->createForHostVersion()->parse($source) ?? []);
        $violations = [];

        foreach (new NodeFinder()->findInstanceOf($statements, Enum_::class) as $enum) {
            $backing = $enum->scalarType?->toLowerString();

            if ($backing !== 'string') {
                $violations[] = ($enum->namespacedName?->toString() ?? 'an enum on line ' . $enum->getStartLine())
                    . ($backing === null ? ' has no backing type' : ' is backed by ' . $backing)
                    . ': declare it as enum X: string and give every case a readable value, so a stored or sent case says what it is';
            }
        }

        return $violations;
    }
}
