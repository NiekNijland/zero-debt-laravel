<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use Psr\Log\LoggerInterface;

final readonly class LogMessageGuard
{
    private const array LOG_FACADES = ['Log', Log::class];

    private const array LOGGERS = [LoggerInterface::class, Logger::class];

    private const array LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

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
        $loggers = TypedReceivers::in($statements, self::LOGGERS, ['log']);
        $held = [];
        $violations = [];

        foreach ($finder->findInstanceOf($statements, Assign::class) as $assign) {
            if ($assign->var instanceof Variable && is_string($assign->var->name) && $finder->findFirst([$assign->expr], self::readsMessage(...)) instanceof Node) {
                $held[] = $assign->var->name;
            }
        }

        foreach ($finder->find($statements, static fn (Node $node): bool => $node instanceof StaticCall || $node instanceof MethodCall || $node instanceof FuncCall) as $call) {
            $arguments = $call instanceof StaticCall || $call instanceof MethodCall || $call instanceof FuncCall ? $this->loggedArguments($call, $loggers) : [];

            foreach ($arguments as $position => $argument) {
                $read = $finder->findFirst([$argument], static fn (Node $node): bool => self::readsMessage($node)
                    || ($node instanceof Variable && is_string($node->name) && in_array($node->name, $held, true)));

                if (! $read instanceof Node) {
                    continue;
                }

                $violations[] = $position === 0
                    ? $call->getStartLine() . ": the log message is built from getMessage(): write a fixed line and pass the error as context, Log::warning('what failed', ['exception' => \$e]), or report(\$e) when it must reach Sentry"
                    : $call->getStartLine() . ": the log context carries getMessage(): pass the error itself, Log::warning('what failed', ['exception' => \$e]), or report(\$e) when it must reach Sentry";
            }
        }

        return $violations;
    }

    private static function readsMessage(Node $node): bool
    {
        return ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)
            && $node->name instanceof Identifier
            && $node->name->toLowerString() === 'getmessage';
    }

    /**
     * @return list<Node>
     */
    private function loggedArguments(StaticCall|MethodCall|FuncCall $call, TypedReceivers $loggers): array
    {
        $values = [];

        foreach ($call->args as $argument) {
            if ($argument instanceof Arg) {
                $values[] = $argument->value;
            }
        }

        if ($call instanceof FuncCall) {
            return $this->isLoggerHelper($call) ? $values : [];
        }

        $name = $call->name instanceof Identifier ? $call->name->toLowerString() : '';
        $receiver = $call instanceof MethodCall ? $call->var : $call;

        while ($receiver instanceof MethodCall || $receiver instanceof NullsafeMethodCall) {
            $receiver = $receiver->var;
        }

        $onLog = ($receiver instanceof StaticCall && $receiver->class instanceof Name && in_array(mb_ltrim($receiver->class->toString(), '\\'), self::LOG_FACADES, true))
            || ($receiver instanceof FuncCall && $this->isLoggerHelper($receiver))
            || $loggers->holds($receiver);

        if (! $onLog) {
            return [];
        }

        return match (true) {
            in_array($name, self::LEVELS, true) => $values,
            $name === 'log' => array_slice($values, 1),
            default => [],
        };
    }

    private function isLoggerHelper(FuncCall $call): bool
    {
        return $call->name instanceof Name && mb_strtolower($call->name->getLast()) === 'logger';
    }
}
