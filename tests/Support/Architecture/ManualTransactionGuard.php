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
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final readonly class ManualTransactionGuard
{
    private const array DB_FACADES = ['DB', DB::class];

    private const array CONNECTIONS = [ConnectionInterface::class, Connection::class, DatabaseManager::class];

    private const string ONE_WAY = 'wrap the work in DB::transaction(fn () => ...), which commits, rolls back and rethrows for you';

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
                $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ':' . $violation;
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
        $finder = new NodeFinder();
        $connections = TypedReceivers::in($statements, self::CONNECTIONS, ['db']);
        $scopes = [$statements];

        foreach ($finder->findInstanceOf($statements, FunctionLike::class) as $function) {
            $scopes[] = $function->getStmts() ?? [];
        }

        $violations = [];

        foreach ($scopes as $scope) {
            $undone = [];

            foreach ($finder->findInstanceOf($scope, TryCatch::class) as $try) {
                foreach ($this->callsNamed($try->finally->stmts ?? [], 'rollback') as $rollBack) {
                    $undone[] = $rollBack->getStartLine();
                }
            }

            foreach ($this->callsNamed($scope, 'begintransaction') as $begin) {
                if ($undone === []) {
                    $violations[$begin->getStartLine() . ' opened'] = $begin->getStartLine() . ': a transaction opened by hand: ' . self::ONE_WAY . '; only a dry run that always rolls back in a finally opens one itself';
                }
            }
        }

        $inFinally = [];

        foreach ($finder->findInstanceOf($statements, TryCatch::class) as $try) {
            foreach ($this->callsNamed($try->finally->stmts ?? [], 'rollback') as $rollBack) {
                $inFinally[] = $rollBack->getStartLine();
            }
        }

        foreach ($this->callsNamed($statements, 'rollback') as $rollBack) {

            $isTransaction = $rollBack->name instanceof Identifier && $rollBack->name->toString() === 'rollBack' || $this->isOnDatabase($rollBack, $connections);

            if ($isTransaction && ! in_array($rollBack->getStartLine(), $inFinally, true)) {
                $violations[$rollBack->getStartLine() . ' rolled back'] = $rollBack->getStartLine() . ': a transaction rolled back by hand: ' . self::ONE_WAY . ' (throw to roll back)';
            }
        }

        foreach ($this->callsNamed($statements, 'commit') as $commit) {
            if ($this->isOnDatabase($commit, $connections)) {
                $violations[$commit->getStartLine() . ' committed'] = $commit->getStartLine() . ': a transaction committed by hand: ' . self::ONE_WAY;
            }
        }

        ksort($violations, SORT_NATURAL);

        return array_values($violations);
    }

    /**
     * @param array<Node> $nodes
     * @return list<StaticCall|MethodCall|NullsafeMethodCall>
     */
    private function callsNamed(array $nodes, string $name): array
    {
        $calls = [];

        foreach (new NodeFinder()->find($nodes, static fn (Node $node): bool => $node instanceof StaticCall || $node instanceof MethodCall || $node instanceof NullsafeMethodCall) as $call) {
            if (($call instanceof StaticCall || $call instanceof MethodCall || $call instanceof NullsafeMethodCall) && $call->name instanceof Identifier && $call->name->toLowerString() === $name) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    private function isOnDatabase(StaticCall|MethodCall|NullsafeMethodCall $call, TypedReceivers $connections): bool
    {
        $receiver = $call instanceof StaticCall ? $call : $call->var;

        while ($receiver instanceof MethodCall || $receiver instanceof NullsafeMethodCall) {
            $receiver = $receiver->var;
        }

        return ($receiver instanceof StaticCall && $receiver->class instanceof Name && in_array(mb_ltrim($receiver->class->toString(), '\\'), self::DB_FACADES, true))
            || $connections->holds($receiver);
    }
}
