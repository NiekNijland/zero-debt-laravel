<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

final readonly class FormRequestGuard
{
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
        $statements = new ParserFactory()->createForHostVersion()->parse($source) ?? [];
        $finder = new NodeFinder();
        $violations = [];

        foreach ($finder->findInstanceOf($statements, Class_::class) as $class) {
            $authorize = $class->getMethod('authorize');

            if ($authorize instanceof ClassMethod && $this->onlyReturnsTrue($authorize) && ! $this->overrides($authorize)) {
                $violations[] = $authorize->getStartLine() . ': authorize() that only returns true: remove the method, a form request without one lets the request through and the route carries the permission or policy; keep it with #[Override] only to switch off the check of a parent request';
            }

            $rules = $class->getMethod('rules')->stmts ?? [];
            $held = [];

            foreach ($finder->findInstanceOf($rules, Assign::class) as $assign) {
                if ($assign->var instanceof Variable && is_string($assign->var->name) && self::isText($assign->expr)) {
                    $held[] = $assign->var->name;
                }
            }

            foreach ($finder->findInstanceOf($rules, ArrayItem::class) as $item) {
                $asString = self::isText($item->value) || ($item->value instanceof Variable && is_string($item->value->name) && in_array($item->value->name, $held, true));

                if ($item->key instanceof String_ && $asString) {
                    $violations[] = $item->value instanceof String_
                        ? $item->getStartLine() . ': the rules of a field as one string: write them as an array, [' . implode(', ', array_map(static fn (string $rule): string => "'" . $rule . "'", explode('|', $item->value->value))) . ']'
                        : $item->getStartLine() . ": the rules of a field as text that is held in a variable or joined: write them as an array, ['required', 'string']";
                }
            }
        }

        return $violations;
    }

    private static function isText(Node $value): bool
    {
        return $value instanceof String_
            || ($value instanceof Concat && self::isText($value->left) && self::isText($value->right));
    }

    private function onlyReturnsTrue(ClassMethod $method): bool
    {
        $statements = $method->stmts ?? [];
        $only = $statements[0] ?? null;

        return count($statements) === 1 && $only instanceof Return_ && $only->expr instanceof ConstFetch && $only->expr->name->toLowerString() === 'true';
    }

    private function overrides(ClassMethod $method): bool
    {
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->getLast() === 'Override') {
                    return true;
                }
            }
        }

        return false;
    }
}
