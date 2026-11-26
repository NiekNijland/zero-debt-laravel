<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final class PhpSource
{
    /**
     * @return list<Stmt>
     */
    public static function statements(string $file): array
    {
        $parser = new ParserFactory()->createForHostVersion();
        $statements = $parser->parse((string) file_get_contents($file)) ?? [];

        $traverser = new NodeTraverser(new NameResolver());

        return array_values(array_filter(
            $traverser->traverse($statements),
            static fn (Node $node): bool => $node instanceof Stmt,
        ));
    }
}
