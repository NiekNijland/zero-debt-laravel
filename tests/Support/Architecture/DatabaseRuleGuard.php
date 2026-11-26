<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final readonly class DatabaseRuleGuard
{
    private const string FACADE = Rule::class;

    private const array RULES = ['exists', 'unique'];

    private const array RULE_CLASSES = [Exists::class => 'exists', Unique::class => 'unique'];

    private const string STRING_RULE = '/(?:^|\|)(exists|unique):/';

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
        $found = [];

        foreach ($finder->find($statements, static fn (Node $node): bool => $node instanceof String_ || $node instanceof InterpolatedStringPart) as $string) {
            if (($string instanceof String_ || $string instanceof InterpolatedStringPart) && preg_match(self::STRING_RULE, $string->value, $match) === 1) {
                $found[] = [$string->getStartLine(), "the string rule '{$match[1]}:': " . $this->replacement($match[1])];
            }
        }

        foreach ($finder->findInstanceOf($statements, New_::class) as $new) {
            $rule = $new->class instanceof Name ? (self::RULE_CLASSES[mb_ltrim($new->class->toString(), '\\')] ?? null) : null;

            if ($rule !== null) {
                $found[] = [$new->getStartLine(), 'new ' . ucfirst($rule) . '(): ' . $this->replacement($rule)];
            }
        }

        foreach ($finder->findInstanceOf($statements, StaticCall::class) as $call) {
            $reason = $this->reasonToFail($call);

            if ($reason !== null) {
                $found[] = [$call->getStartLine(), $reason];
            }
        }

        sort($found);

        return array_map(static fn (array $violation): string => $violation[0] . ': ' . $violation[1], $found);
    }

    private function reasonToFail(StaticCall $call): ?string
    {
        $rule = $call->name instanceof Identifier ? $call->name->toLowerString() : '';

        if (! $call->class instanceof Name || mb_ltrim($call->class->toString(), '\\') !== self::FACADE || ! in_array($rule, self::RULES, true)) {
            return null;
        }

        return $this->model($call) === null ? "Rule::{$rule}() without a model class as its first argument: " . $this->replacement($rule) : null;
    }

    private function replacement(string $rule): string
    {
        return "write Rule::{$rule}(Model::class, 'column'): the model class names the table, a table name or a string rule does not follow a rename";
    }

    /**
     * @return class-string<Model>|null
     */
    private function model(StaticCall $call): ?string
    {
        $first = $call->args[0] ?? null;
        $value = $first instanceof Arg && ! $first->unpack ? $first->value : null;

        if (! $value instanceof ClassConstFetch || ! $value->class instanceof Name || ! $value->name instanceof Identifier || $value->name->toLowerString() !== 'class') {
            return null;
        }

        $class = mb_ltrim($value->class->toString(), '\\');

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }
}
