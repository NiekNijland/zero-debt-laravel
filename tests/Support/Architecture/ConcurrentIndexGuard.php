<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use Tests\Support\Globs;

final readonly class ConcurrentIndexGuard
{
    private const string MESSAGE = 'an index or foreign key built on a table that already exists, which blocks writes: create the index with CREATE INDEX CONCURRENTLY outside a transaction ($withinTransaction = false), or add the foreign key NOT VALID and validate it afterwards';

    /**
     * @param string $folder the migration folder under the root
     * @param string $firstFile the file name (or its date prefix) of the first migration under the rule
     */
    public function __construct(
        private string $root,
        private string $folder,
        private string $firstFile,
    ) {
    }

    /**
     * @return list<string> `table: message` per table of the migration that gets a blocking index
     */
    public function inSource(string $source): array
    {
        return array_map(static fn (string $table): string => $table . ': ' . self::MESSAGE, $this->blockingTables($source));
    }

    /**
     * @return list<string> `file: table: message` per blocking index in a migration from the first file on, relative to the root
     */
    public function violations(): array
    {
        $violations = [];

        if (! is_dir($this->root . '/' . $this->folder)) {
            throw new InvalidArgumentException('The directory ' . $this->folder . ' does not exist: a guard over a missing folder would pass without reading a file');
        }

        foreach (Globs::of($this->root . '/' . $this->folder . '/*.php') as $path) {
            if (strcmp(basename($path), $this->firstFile) < 0) {
                continue;
            }

            foreach ($this->inSource((string) file_get_contents($path)) as $violation) {
                $violations[] = $this->folder . '/' . basename($path) . ': ' . $violation;
            }
        }

        sort($violations);

        return $violations;
    }

    /**
     * @return list<Node\Stmt>
     */
    private function upStatements(NodeFinder $finder, string $source): array
    {
        $methods = [];

        foreach ($finder->findInstanceOf(PhpSource::parse($source), ClassMethod::class) as $method) {
            $methods[mb_strtolower($method->name->toString())] = $method->stmts ?? [];
        }

        $statements = [];
        $pending = ['up'];
        $followed = [];

        while ($pending !== []) {
            $name = array_pop($pending);

            if (isset($followed[$name]) || ! isset($methods[$name])) {
                continue;
            }

            $followed[$name] = true;
            array_push($statements, ...$methods[$name]);

            foreach ($finder->find($methods[$name], static fn (Node $node): bool => (
                ($node instanceof MethodCall && $node->var instanceof Variable && $node->var->name === 'this')
                || ($node instanceof StaticCall && $node->class instanceof Name && in_array(mb_strtolower($node->class->toString()), ['self', 'static'], true))
            ) && $node->name instanceof Identifier) as $call) {
                if (($call instanceof MethodCall || $call instanceof StaticCall) && $call->name instanceof Identifier) {
                    $pending[] = mb_strtolower($call->name->toString());
                }
            }
        }

        return $statements;
    }

    /**
     * @param list<Node\Stmt> $statements
     * @return list<string>
     */
    private function sqlStrings(NodeFinder $finder, array $statements): array
    {
        $sql = [];

        foreach ($finder->find($statements, static fn (Node $node): bool => $node instanceof String_ || $node instanceof InterpolatedString) as $string) {
            if ($string instanceof String_) {
                $sql[] = $string->value;
            } elseif ($string instanceof InterpolatedString) {
                $sql[] = implode('?', array_map(static fn (Node $part): string => $part instanceof InterpolatedStringPart ? $part->value : '', $string->parts));
            }
        }

        return $sql;
    }

    /**
     * @return list<string>
     */
    private function blockingTables(string $source): array
    {
        $finder = new NodeFinder();
        $statements = $this->upStatements($finder, $source);

        $named = static fn (Node $node, array $names): bool => ($node instanceof StaticCall || $node instanceof MethodCall)
            && $node->name instanceof Identifier
            && in_array(mb_strtolower($node->name->toString()), $names, true);
        $blueprintCalls = static fn (string $method): array => array_values(array_filter(
            $finder->find($statements, static fn (Node $node): bool => ($node instanceof StaticCall || $node instanceof MethodCall)
                && $named($node, [$method])
                && array_filter($node->getArgs(), static fn (Arg $argument): bool => $argument->value instanceof Closure || $argument->value instanceof ArrowFunction) !== []),
            static fn (Node $node): bool => $node instanceof StaticCall || $node instanceof MethodCall,
        ));
        $tableName = static function (StaticCall|MethodCall $call): ?string {
            $first = $call->getArgs()[0]->value ?? null;

            return $first instanceof String_ ? $first->value : null;
        };
        $sqlTable = static fn (string $pattern, string $sql): ?string => preg_match($pattern, $sql, $match) === 1
            ? mb_trim((string) mb_strrchr('.' . $match[1], '.'), '."')
            : null;

        $sql = $this->sqlStrings($finder, $statements);

        $created = array_filter([
            ...array_map($tableName, $blueprintCalls('create')),
            ...array_map(static fn (string $query): ?string => $sqlTable('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([\w."]+)/i', $query), $sql),
        ], static fn (?string $table): bool => $table !== null && $table !== '');
        $blocking = [];

        foreach ($blueprintCalls('table') as $call) {
            $indexCall = $finder->findFirst($call->getArgs(), static fn (Node $node): bool => $named($node, [
                'index', 'unique', 'primary', 'foreign', 'constrained', 'rawindex', 'fulltext', 'spatialindex',
                'morphs', 'nullablemorphs', 'numericmorphs', 'nullablenumericmorphs',
                'uuidmorphs', 'nullableuuidmorphs', 'ulidmorphs', 'nullableulidmorphs',
            ]));

            if ($indexCall instanceof Node) {
                $blocking[] = $tableName($call) ?? '(dynamic table name)';
            }
        }

        foreach ($sql as $query) {
            if (preg_match('/CREATE\s+(UNIQUE\s+)?INDEX\s+(?!CONCURRENTLY)/i', $query) === 1) {
                $blocking[] = $sqlTable('/\bON\s+(?:ONLY\s+)?([\w."]+)/i', $query) ?? '(raw SQL)';
            }

            if (preg_match('/FOREIGN\s+KEY/i', $query) === 1 && preg_match('/NOT\s+VALID/i', $query) !== 1) {
                $blocking[] = $sqlTable('/ALTER\s+TABLE\s+(?:IF\s+EXISTS\s+)?(?:ONLY\s+)?([\w."]+)/i', $query)
                    ?? $sqlTable('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([\w."]+)/i', $query)
                    ?? '(raw SQL)';
            }
        }

        return array_values(array_unique(array_diff($blocking, $created)));
    }
}
