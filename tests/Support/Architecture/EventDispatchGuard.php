<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Events\Dispatchable;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;

final readonly class EventDispatchGuard
{
    private const string DISPATCHABLE = Dispatchable::class;

    private const array DISPATCHERS = [DispatcherContract::class, Dispatcher::class];

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
        $statements = PhpSource::resolved($source);
        $dispatchers = TypedReceivers::in($statements, self::DISPATCHERS, ['events']);
        $violations = [];

        foreach (new NodeFinder()->find($statements, static fn (Node $node): bool => $node instanceof StaticCall || $node instanceof MethodCall || $node instanceof Class_) as $node) {
            $violation = match (true) {
                $node instanceof Class_ => $this->dispatchableClass($node),
                $node instanceof StaticCall => $this->staticDispatch($node),
                $node instanceof MethodCall => $this->dispatcherDispatch($node, $dispatchers),
                default => null,
            };

            if ($violation !== null) {
                $violations[] = $node->getStartLine() . ': ' . $violation;
            }
        }

        return $violations;
    }

    private function dispatchableClass(Class_ $class): ?string
    {
        foreach ($class->getTraitUses() as $use) {
            foreach ($use->traits as $trait) {
                if (mb_ltrim($trait->toString(), '\\') === self::DISPATCHABLE) {
                    return 'a class uses ' . self::DISPATCHABLE . ', and the refactoring set rewrites X::dispatch(...) of such a class into the banned event() helper: remove the trait and dispatch with Event::dispatch(new <X>Event(...))';
                }
            }
        }

        foreach ($class->getMethods() as $method) {
            if ($method->isStatic() && $method->name->toLowerString() === 'dispatch' && $class->namespacedName instanceof Name && $this->isEvent($class->namespacedName->toString())) {
                return 'an event class declares its own dispatch(): remove it and dispatch with Event::dispatch(new <X>Event(...))';
            }
        }

        return null;
    }

    private function staticDispatch(StaticCall $call): ?string
    {
        if (! $call->class instanceof Name || ! $call->name instanceof Identifier || $call->name->toLowerString() !== 'dispatch') {
            return null;
        }

        $class = mb_ltrim($call->class->toString(), '\\');

        return $this->isEvent($class)
            ? $call->class->getLast() . '::dispatch(...) calls the dispatch() of an event class: write Event::dispatch(new ' . $call->class->getLast() . '(...)) with the same arguments (the event has no Dispatchable trait)'
            : null;
    }

    private function isEvent(string $class): bool
    {
        return str_starts_with($class, 'App\\') && (str_contains($class, '\\Events\\') || str_ends_with($class, 'Event'));
    }

    private function dispatcherDispatch(MethodCall $call, TypedReceivers $dispatchers): ?string
    {
        $dispatches = $call->name instanceof Identifier && $call->name->toLowerString() === 'dispatch';
        $event = ($call->getArgs()[0] ?? null)?->value;

        return $dispatches && $dispatchers->holds($call->var) && $event instanceof New_ && $event->class instanceof Name && $this->isEvent(mb_ltrim($event->class->toString(), '\\'))
            ? 'the dispatcher(new ' . $event->class->getLast() . '(...)) is no facade call: write Event::dispatch(new ' . $event->class->getLast() . '(...))'
            : null;
    }
}
