<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Tests\Support\Globs;

final readonly class MigrationShapeGuard
{
    private const array ON_DELETE = ['ondelete', 'cascadeondelete', 'restrictondelete', 'nullondelete', 'noactionondelete'];

    public function __construct(
        private string $root,
        private string $migrations,
        private string $firstFile,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach (Globs::of($this->root . '/' . $this->migrations . '/*.php') as $file) {
            if (strcmp(basename($file), $this->firstFile) < 0) {
                continue;
            }

            foreach ($this->inSource((string) file_get_contents($file)) as $violation) {
                $violations[] = $this->migrations . '/' . basename($file) . ':' . $violation;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $finder = new NodeFinder();
        $violations = [];

        foreach ($finder->findInstanceOf(new ParserFactory()->createForHostVersion()->parse($source) ?? [], Class_::class) as $class) {
            if ($class->getMethod('up') === null) {
                continue;
            }

            $down = $class->getMethod('down');

            if ($down === null) {
                $violations[] = $class->getStartLine() . ': a migration without down(): add public function down(): void that undoes up(), so a failed deploy can roll back';
            } elseif (($down->stmts ?? []) === [] && $this->changesSchema($class)) {
                $violations[] = $down->getStartLine() . ': an empty down() in a migration that changes the schema: undo up() in it (drop the table, column or index it adds); only a migration that changes rows alone leaves down() empty';
            }

            $calls = $finder->findInstanceOf($class, MethodCall::class);
            $inner = array_map(static fn (MethodCall $call): int => spl_object_id($call->var), $calls);

            foreach ($calls as $call) {
                if (in_array(spl_object_id($call), $inner, true)) {
                    continue;
                }

                $chain = $this->chain($call);

                if ((in_array('constrained', $chain, true) || in_array('references', $chain, true)) && array_intersect($chain, self::ON_DELETE) === []) {
                    $violations[] = $call->getStartLine() . ': a foreign key without its on-delete action: end it with ->cascadeOnDelete(), ->nullOnDelete() or ->restrictOnDelete(), so what happens to the row when its parent goes is a choice';
                }

                if (in_array('boolean', $chain, true) && ! in_array('default', $chain, true)) {
                    $violations[] = $call->getStartLine() . ': a boolean column without a default: add ->default(false) or ->default(true), so a row written by the previous release during the deploy still fits';
                }
            }
        }

        sort($violations, SORT_NATURAL);

        return array_values(array_unique($violations));
    }

    private function changesSchema(Class_ $class): bool
    {
        $finder = new NodeFinder();
        $methods = array_filter($class->getMethods(), static fn (ClassMethod $method): bool => $method->name->toLowerString() !== 'down');

        foreach ($finder->findInstanceOf($methods, StaticCall::class) as $call) {
            if ($call->class instanceof Name && $call->class->getLast() === 'Schema') {
                return true;
            }
        }

        return array_any($finder->findInstanceOf($methods, String_::class), fn (String_ $string): bool => preg_match('/^\s*(create|alter|drop|truncate)\s/i', $string->value) === 1);
    }

    /**
     * @return list<string>
     */
    private function chain(Node $call): array
    {
        $names = [];

        while ($call instanceof MethodCall) {
            if ($call->name instanceof Identifier) {
                $names[] = $call->name->toLowerString();
            }

            $call = $call->var;
        }

        return $names;
    }
}
