<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RuntimeException;

final class PhpSource
{
    /**
     * @return list<Stmt>
     */
    public static function statements(string $file): array
    {
        throw_unless(is_file($file), RuntimeException::class, "the file {$file} does not exist: a guard that reads a named file fails when it is renamed, it does not read an empty tree");

        return array_values(array_filter(
            self::resolved((string) file_get_contents($file)),
            static fn (Node $node): bool => $node instanceof Stmt,
        ));
    }

    /**
     * @return list<Stmt> the statements of a source text, names as written
     */
    public static function parse(string $source): array
    {
        return array_values(new ParserFactory()->createForHostVersion()->parse($source) ?? []);
    }

    /**
     * @param bool $preserveOriginal keep the name as written next to the resolved one (a reader that reports the spelling needs it)
     * @return list<Node> the statements of a source text with every name resolved
     */
    public static function resolved(string $source, bool $preserveOriginal = false): array
    {
        return self::traverse(self::parse($source), new NameResolver(null, $preserveOriginal ? ['preserveOriginalNames' => true] : []));
    }

    /**
     * @param array<Node> $statements
     * @return list<Node>
     */
    public static function traverse(array $statements, NodeVisitor ...$visitors): array
    {
        return array_values(new NodeTraverser(...$visitors)->traverse($statements));
    }
}
