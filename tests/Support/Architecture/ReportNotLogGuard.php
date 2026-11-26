<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Override;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
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
use Psr\Log\LoggerInterface;

final readonly class ReportNotLogGuard
{
    private const array LOG_FACADES = ['Log', Log::class];

    private const array LOGGERS = [LoggerInterface::class, Logger::class];

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
     * @return list<string> `line N: message` per violation in the source, in line order
     */
    public function inSource(string $source): array
    {
        $statements = $this->statements($source);
        $loggers = TypedReceivers::in($statements, self::LOGGERS, ['log']);
        $found = [];

        foreach ($this->severeLogInCatchLines($statements, $loggers) as $line) {
            $found[$line . ':a'] = 'line ' . $line . ': ' . self::SEVERE_MESSAGE;
        }

        foreach ($this->tenantIdLogKeyLines($statements, $loggers) as $line) {
            $found[$line . ':b'] = 'line ' . $line . ': ' . self::TENANT_MESSAGE;
        }

        ksort($found, SORT_NATURAL);

        return array_values($found);
    }

    /**
     * @return list<string> `file:` before each message of the source, relative to the root
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

    private static function onLog(StaticCall|MethodCall|NullsafeMethodCall|FuncCall $call, TypedReceivers $loggers): bool
    {
        if ($call instanceof FuncCall) {
            return self::isLoggerHelper($call) && $call->getArgs() !== [];
        }

        $receiver = $call instanceof StaticCall ? $call : $call->var;

        while ($receiver instanceof MethodCall || $receiver instanceof NullsafeMethodCall) {
            $receiver = $receiver->var;
        }

        return ($receiver instanceof StaticCall && $receiver->class instanceof Name && in_array(mb_ltrim($receiver->class->toString(), '\\'), self::LOG_FACADES, true))
            || ($receiver instanceof FuncCall && self::isLoggerHelper($receiver))
            || $loggers->holds($receiver);
    }

    private static function isLoggerHelper(FuncCall $call): bool
    {
        return $call->name instanceof Name && mb_strtolower($call->name->getLast()) === 'logger';
    }

    /**
     * @param list<Node> $statements
     * @return list<int>
     */
    private function severeLogInCatchLines(array $statements, TypedReceivers $loggers): array
    {
        $lines = [];

        foreach (new NodeFinder()->findInstanceOf($statements, Catch_::class) as $catch) {
            foreach ($this->callsOfTheCatch($catch) as $call) {
                if (in_array($this->level($call, $loggers), self::SEVERE_LEVELS, true)) {
                    $lines[] = $call->getStartLine();
                }
            }
        }

        sort($lines);

        return array_values(array_unique($lines));
    }

    /**
     * @param list<Node> $statements
     * @return list<int>
     */
    private function tenantIdLogKeyLines(array $statements, TypedReceivers $loggers): array
    {
        $lines = [];
        $finder = new NodeFinder();

        foreach ($finder->find($statements, static fn (Node $node): bool => ($node instanceof StaticCall || $node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof FuncCall) && self::onLog($node, $loggers)) as $call) {
            if (! $call instanceof StaticCall && ! $call instanceof MethodCall && ! $call instanceof NullsafeMethodCall && ! $call instanceof FuncCall) {
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
     * @return list<StaticCall|MethodCall|NullsafeMethodCall|FuncCall>
     */
    private function callsOfTheCatch(Catch_ $catch): array
    {
        $caught = $catch->var instanceof Variable && is_string($catch->var->name) ? $catch->var->name : null;
        $visitor = new class($caught) extends NodeVisitorAbstract
        {
            /** @var list<StaticCall|MethodCall|NullsafeMethodCall|FuncCall> */
            public array $calls = [];

            public function __construct(private readonly ?string $caught)
            {
            }

            #[Override]
            public function enterNode(Node $node): ?int
            {
                if ($node instanceof FunctionLike && ! $this->mentionsCaught($node)) {
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof StaticCall || $node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof FuncCall) {
                    $this->calls[] = $node;
                }

                return null;
            }

            private function mentionsCaught(Node $function): bool
            {
                return $this->caught !== null
                    && new NodeFinder()->findFirst([$function], fn (Node $node): bool => $node instanceof Variable && $node->name === $this->caught) instanceof Node;
            }
        };

        new NodeTraverser($visitor)->traverse($catch->stmts);

        return $visitor->calls;
    }

    private function level(StaticCall|MethodCall|NullsafeMethodCall|FuncCall $call, TypedReceivers $loggers): ?string
    {
        if ($call instanceof FuncCall || ! $call->name instanceof Identifier || ! self::onLog($call, $loggers)) {
            return null;
        }

        $name = $call->name->toLowerString();

        if ($name !== 'log') {
            return $name;
        }

        $first = ($call->getArgs()[0] ?? null)?->value;

        return $first instanceof String_ ? mb_strtolower($first->value) : null;
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
