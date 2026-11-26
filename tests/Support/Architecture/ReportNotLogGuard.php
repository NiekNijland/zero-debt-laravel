<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Support\Facades\Log;
use Override;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

final readonly class ReportNotLogGuard
{
    private const array LOG_FACADES = ['Log', Log::class];

    private const array SEVERE_LEVELS = ['warning', 'error', 'critical', 'alert', 'emergency'];

    private const string SEVERE_MESSAGE = 'a log call of level warning or higher that handles a caught exception: report the exception with report() and let the handler log it';

    private const string TENANT_MESSAGE = 'a tenant_id key written into a log context by hand: the log context adds the tenant, leave the key out';

    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
    ) {
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $found = [];

        foreach ($this->severeLogInCatchLines($source) as $line) {
            $found[$line . ':a'] = 'line ' . $line . ': ' . self::SEVERE_MESSAGE;
        }

        foreach ($this->tenantIdLogKeyLines($source) as $line) {
            $found[$line . ':b'] = 'line ' . $line . ': ' . self::TENANT_MESSAGE;
        }

        ksort($found, SORT_NATURAL);

        return array_values($found);
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->folder) as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $message) {
                $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ': ' . $message;
            }
        }

        sort($violations);

        return $violations;
    }

    private static function logMethod(StaticCall|MethodCall $call): ?string
    {
        if (! $call->name instanceof Identifier) {
            return null;
        }

        $receiver = $call instanceof MethodCall ? $call->var : $call;

        if ($receiver instanceof StaticCall && $receiver->class instanceof Name && in_array($receiver->class->toString(), self::LOG_FACADES, true)) {
            return $call->name->toLowerString();
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private function severeLogInCatchLines(string $source): array
    {
        $lines = [];

        foreach (new NodeFinder()->findInstanceOf($this->statements($source), Catch_::class) as $catch) {
            foreach ($this->callsOutsideClosures($catch->stmts) as $call) {
                if (in_array(self::logMethod($call), self::SEVERE_LEVELS, true)) {
                    $lines[] = $call->getStartLine();
                }
            }
        }

        sort($lines);

        return array_values(array_unique($lines));
    }

    /**
     * @return list<int>
     */
    private function tenantIdLogKeyLines(string $source): array
    {
        $lines = [];
        $finder = new NodeFinder();

        foreach ($finder->find($this->statements($source), static fn (Node $node): bool => ($node instanceof StaticCall || $node instanceof MethodCall) && self::logMethod($node) !== null) as $call) {
            if (! $call instanceof StaticCall && ! $call instanceof MethodCall) {
                continue;
            }

            foreach ($finder->findInstanceOf($call->args, ArrayItem::class) as $item) {
                if ($item->key instanceof String_ && $item->key->value === 'tenant_id') {
                    $lines[] = $item->getStartLine();
                }
            }
        }

        sort($lines);

        return array_values(array_unique($lines));
    }

    /**
     * @param array<Node> $statements
     * @return list<StaticCall|MethodCall>
     */
    private function callsOutsideClosures(array $statements): array
    {
        $visitor = new class() extends NodeVisitorAbstract
        {
            /** @var list<StaticCall|MethodCall> */
            public array $calls = [];

            #[Override]
            public function enterNode(Node $node): ?int
            {
                if ($node instanceof FunctionLike) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof StaticCall || $node instanceof MethodCall) {
                    $this->calls[] = $node;
                }

                return null;
            }
        };

        new NodeTraverser($visitor)->traverse($statements);

        return $visitor->calls;
    }

    /**
     * @return list<Node>
     */
    private function statements(string $source): array
    {
        $statements = new ParserFactory()->createForHostVersion()->parse($source) ?? [];

        return array_values(new NodeTraverser(new NameResolver())->traverse($statements));
    }
}
