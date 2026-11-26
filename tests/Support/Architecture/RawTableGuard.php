<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final readonly class RawTableGuard
{
    private const array RAW_METHODS = ['table', 'select', 'selectone', 'selectresultsets', 'scalar', 'statement', 'unprepared', 'raw', 'insert', 'update', 'delete', 'affectingstatement', 'cursor'];

    private const array FACADES = ['DB', DB::class];

    private const array CONNECTIONS = [ConnectionInterface::class, Connection::class, DatabaseManager::class];

    private const string MESSAGE = 'a raw table or statement without a model: reach the table through its Eloquent model; raw SQL belongs in a migration or in the one allowed lock class';

    /**
     * @param list<string> $directories folders under the root that the tree scan reads, relative to it; some may be absent
     * @param list<string> $allowedFiles files relative to the root that may hold raw SQL (an advisory lock has no Eloquent form)
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private array $directories,
        private array $allowedFiles,
    ) {
    }

    /**
     * @return list<string> `line N: message` per raw call in the source
     */
    public function inSource(string $source): array
    {
        return array_map(static fn (int $line): string => 'line ' . $line . ': ' . self::MESSAGE, $this->lines($source));
    }

    /**
     * @return list<string> `file:line: message` relative to the root
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->directories as $directory) {
            foreach ($this->inspector->phpFiles($this->root . '/' . $directory, mayBeAbsent: true) as $file) {
                $relative = mb_substr($file, mb_strlen($this->root) + 1);

                if (in_array($relative, $this->allowedFiles, true)) {
                    continue;
                }

                foreach ($this->lines((string) file_get_contents($file)) as $line) {
                    $violations[] = $relative . ':' . $line . ': ' . self::MESSAGE;
                }
            }
        }

        sort($violations);

        return $violations;
    }

    /**
     * @return list<int>
     */
    private function lines(string $source): array
    {
        $statements = new NodeTraverser(new NameResolver())->traverse(new ParserFactory()->createForHostVersion()->parse($source) ?? []);
        $lines = [];
        $connections = TypedReceivers::in($statements, self::CONNECTIONS, ['db']);

        foreach (new NodeFinder()->find($statements, static fn (Node $node): bool => $node instanceof StaticCall || $node instanceof MethodCall || $node instanceof NullsafeMethodCall) as $call) {
            if (! $call instanceof StaticCall && ! $call instanceof MethodCall && ! $call instanceof NullsafeMethodCall) {
                continue;
            }

            if (in_array($this->name($call), self::RAW_METHODS, true) && $this->reachesDatabase($call, $connections)) {
                $lines[] = $call->getStartLine();
            }
        }

        sort($lines);

        return $lines;
    }

    private function reachesDatabase(StaticCall|MethodCall|NullsafeMethodCall $call, TypedReceivers $connections): bool
    {
        if ($call instanceof StaticCall) {
            return $this->isFacade($call);
        }

        $receiver = $call->var;

        if ($receiver instanceof StaticCall) {
            return $this->isFacade($receiver) && $this->name($receiver) === 'connection';
        }

        if (($receiver instanceof MethodCall || $receiver instanceof NullsafeMethodCall) && $this->name($receiver) === 'connection') {
            $receiver = $receiver->var;
        }

        return $connections->holds($receiver);
    }

    private function isFacade(StaticCall $call): bool
    {
        return $call->class instanceof Name && in_array(mb_ltrim($call->class->toString(), '\\'), self::FACADES, true);
    }

    private function name(StaticCall|MethodCall|NullsafeMethodCall $call): string
    {
        return $call->name instanceof Identifier ? mb_strtolower($call->name->toString()) : '';
    }
}
