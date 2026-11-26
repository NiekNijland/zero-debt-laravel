<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Support\Facades\DB;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final readonly class ThinControllerGuard
{
    private const array WRITES = [
        'save', 'savequietly', 'update', 'updatequietly', 'updateorcreate', 'updateorinsert', 'firstorcreate', 'create', 'createquietly', 'createmany',
        'delete', 'deletequietly', 'forcedelete', 'forcedeletequietly', 'restore', 'restorequietly', 'attach', 'sync', 'syncwithoutdetaching',
        'detach', 'updateexistingpivot', 'insert', 'insertorignore', 'insertgetid', 'upsert', 'increment', 'decrement', 'incrementeach',
        'decrementeach', 'lockforupdate', 'sharedlock', 'transaction', 'begintransaction',
    ];

    private const array DB_FACADES = ['DB', DB::class];

    /**
     * @param list<string> $collaboratorFolders
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
        private int $maxInvokeLines,
        private array $collaboratorFolders,
    ) {
    }

    /**
     * @return list<string>
     */
    public function inSource(string $source): array
    {
        $statements = new NodeTraverser(new NameResolver())->traverse(new ParserFactory()->createForHostVersion()->parse($source) ?? []);
        $finder = new NodeFinder();
        $collaborators = $this->collaborators($finder, $statements);
        $found = [];

        $calls = $finder->find(
            $statements,
            static fn (Node $node): bool => $node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall,
        );

        foreach ($calls as $call) {
            if (! ($call instanceof MethodCall || $call instanceof NullsafeMethodCall || $call instanceof StaticCall)) {
                continue;
            }

            if ($call instanceof StaticCall && $call->class instanceof Name && in_array($call->class->toString(), self::DB_FACADES, true)) {
                $found[] = [$call->getStartLine(), $this->message('DB::')];

                continue;
            }

            $name = $call->name instanceof Identifier ? mb_strtolower($call->name->toString()) : '';

            if (! in_array($name, self::WRITES, true)) {
                continue;
            }

            if (! $call instanceof StaticCall && $this->callsCollaborator($call->var, $collaborators)) {
                continue;
            }

            $found[] = [$call->getStartLine(), $this->message($name . '()')];
        }

        foreach ($finder->findInstanceOf($statements, ClassMethod::class) as $method) {
            $length = $method->getEndLine() - $method->getStartLine() + 1;

            if ($method->name->toLowerString() === '__invoke' && $length > $this->maxInvokeLines) {
                $found[] = [$method->getStartLine(), '__invoke has ' . $length . ' lines, the limit is ' . $this->maxInvokeLines . ': move the logic into an action of the module and call it'];
            }
        }

        usort($found, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_map(static fn (array $entry): string => 'line ' . $entry[0] . ': ' . $entry[1], $found);
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->folder) as $file) {
            foreach ($this->inSource((string) file_get_contents($file)) as $violation) {
                $violations[] = mb_substr($file, mb_strlen($this->root) + 1) . ':' . $violation;
            }
        }

        return $violations;
    }

    private function message(string $call): string
    {
        return $call . ' in a controller: move the write, lock or transaction into an action of the module and call the action';
    }

    /**
     * @param array<Node> $statements
     * @return list<string>
     */
    private function collaborators(NodeFinder $finder, array $statements): array
    {
        $collaborators = [];

        foreach ($finder->findInstanceOf($statements, Class_::class) as $class) {
            foreach ($class->getProperties() as $property) {
                if ($this->isCollaborator($property->type)) {
                    foreach ($property->props as $item) {
                        $collaborators[] = $item->name->toString();
                    }
                }
            }

            $constructor = $class->getMethod('__construct');

            foreach ($constructor->params ?? [] as $param) {
                if ($param->flags !== 0 && $param->var instanceof Variable && is_string($param->var->name) && $this->isCollaborator($param->type)) {
                    $collaborators[] = $param->var->name;
                }
            }
        }

        return $collaborators;
    }

    private function isCollaborator(?Node $type): bool
    {
        if ($type instanceof NullableType) {
            $type = $type->type;
        }

        if (! $type instanceof Name) {
            return false;
        }

        $class = '\\' . $type->toString();

        return array_any($this->collaboratorFolders, fn (string $folder): bool => str_contains($class, $folder));
    }

    /**
     * @param list<string> $collaborators
     */
    private function callsCollaborator(Node $receiver, array $collaborators): bool
    {
        return $receiver instanceof PropertyFetch
            && $receiver->var instanceof Variable
            && $receiver->var->name === 'this'
            && $receiver->name instanceof Identifier
            && in_array($receiver->name->toString(), $collaborators, true);
    }
}
