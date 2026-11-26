<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionMethod;

/**
 * @phpstan-type Info array{name: string, path: string, line: int, kind: 'class'|'interface'|'trait', abstract: bool, parents: list<string>, uses: list<string>, methods: list<string>, callable: list<string>, properties: array<string, 'default'|'instance'>, attributes: list<string>}
 */
final readonly class ClassGraph
{
    /**
     * @param array<string, Info> $types
     */
    private function __construct(private array $types)
    {
    }

    /**
     * @param array<string, string> $sources
     */
    public static function of(array $sources): self
    {
        $types = [];

        foreach ($sources as $path => $source) {
            $statements = PhpSource::resolved($source);

            foreach (new NodeFinder()->findInstanceOf($statements, ClassLike::class) as $type) {
                $info = self::describe($type, $path);
                $types[$type->namespacedName === null ? $info['name'] . '@' . $path : $info['name']] = $info;
            }
        }

        return new self($types);
    }

    /**
     * @return list<Info>
     */
    public function concreteClasses(): array
    {
        return array_values(array_filter($this->types, static fn (array $info): bool => $info['kind'] === 'class' && ! $info['abstract']));
    }

    /**
     * @param Info $info
     * @param list<string> $targets
     */
    public function reaches(array $info, array $targets): ?string
    {
        foreach ($this->ancestors($info['parents']) as $ancestor) {
            if (in_array($ancestor, $targets, true)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * @param Info $info
     */
    public function hasMethod(array $info, string $method, bool $callable = false): bool
    {
        foreach ($this->lineage($info) as $name => $type) {
            if ($type !== null && in_array(mb_strtolower($method), $type[$callable ? 'callable' : 'methods'], true)) {
                return true;
            }

            if ($type === null && (class_exists($name) || trait_exists($name)) && method_exists($name, $method)) {
                if (! $callable) {
                    return true;
                }

                foreach (new ReflectionClass($name)->getMethods(ReflectionMethod::IS_PUBLIC) as $public) {
                    if (strcasecmp($public->getName(), $method) === 0 && ! $public->isStatic() && ! $public->isAbstract()) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param Info $info
     * @return 'default'|'instance'|null
     */
    public function property(array $info, string $property): ?string
    {
        $found = null;

        foreach ($this->lineage($info) as $name => $type) {
            $declared = $type['properties'][$property] ?? null;

            if ($type === null && (class_exists($name) || trait_exists($name))) {
                $declared = $this->vendorProperty($name, $property);
            }

            if ($declared === 'instance') {
                return 'instance';
            }

            $found ??= $declared;
        }

        return $found;
    }

    /**
     * @param Info $info
     */
    public function hasAttribute(array $info, string $shortName): bool
    {
        foreach ($this->lineage($info) as $type) {
            foreach ($type['attributes'] ?? [] as $attribute) {
                if (str_ends_with('\\' . $attribute, '\\' . $shortName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return Info
     */
    private static function describe(ClassLike $type, string $path): array
    {
        $parents = [];

        if ($type instanceof Class_) {
            $parents = array_values([...($type->extends instanceof Name ? [$type->extends->toString()] : []), ...array_map(static fn (Name $name): string => $name->toString(), $type->implements)]);
        } elseif ($type instanceof Interface_) {
            $parents = array_values(array_map(static fn (Name $name): string => $name->toString(), $type->extends));
        }

        $uses = [];

        foreach ($type->getTraitUses() as $use) {
            foreach ($use->traits as $trait) {
                $uses[] = $trait->toString();
            }
        }

        $properties = [];

        foreach ($type->getProperties() as $property) {
            foreach ($property->props as $item) {
                $properties[$item->name->toString()] = $item->default === null ? 'instance' : 'default';
            }
        }

        $constructor = $type->getMethod('__construct');

        foreach ($constructor->params ?? [] as $parameter) {
            if ($parameter->flags !== 0 && $parameter->var instanceof Variable && is_string($parameter->var->name)) {
                $properties[$parameter->var->name] = 'instance';
            }
        }

        $attributes = [];

        foreach ($type->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                $attributes[] = $attribute->name->toString();
            }
        }

        return [
            'name' => $type->namespacedName?->toString() ?? 'an anonymous class on line ' . $type->getStartLine(),
            'path' => $path,
            'line' => $type->getStartLine(),
            'kind' => $type instanceof Interface_ ? 'interface' : ($type instanceof Class_ ? 'class' : 'trait'),
            'abstract' => $type instanceof Class_ && $type->isAbstract(),
            'parents' => $parents,
            'uses' => $uses,
            'methods' => $type instanceof Interface_ ? [] : array_map(static fn (ClassMethod $method): string => $method->name->toLowerString(), $type->getMethods()),
            'callable' => $type instanceof Interface_ ? [] : array_values(array_map(static fn (ClassMethod $method): string => $method->name->toLowerString(), array_filter($type->getMethods(), static fn (ClassMethod $method): bool => $method->isPublic() && ! $method->isStatic() && ! $method->isAbstract()))),
            'properties' => $properties,
            'attributes' => $attributes,
        ];
    }

    /**
     * @return 'default'|'instance'|null
     */
    private function vendorProperty(string $name, string $property): ?string
    {
        if (! class_exists($name) && ! trait_exists($name)) {
            return null;
        }

        $file = new ReflectionClass($name)->getFileName();

        if ($file === false) {
            return null;
        }

        $parsed = self::of([$file => (string) file_get_contents($file)])->types[$name] ?? null;

        return $parsed === null ? null : new self([$name => $parsed, ...$this->types])->property($parsed, $property);
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private function ancestors(array $names): array
    {
        $found = [];
        $queue = $names;

        while ($queue !== []) {
            $name = mb_ltrim(array_shift($queue), '\\');

            if (in_array($name, $found, true)) {
                continue;
            }

            $found[] = $name;

            if (isset($this->types[$name])) {
                array_push($queue, ...$this->types[$name]['parents']);
            } elseif (class_exists($name) || interface_exists($name)) {
                $parents = class_parents($name);
                $interfaces = class_implements($name);

                array_push($queue, ...array_values($parents === false ? [] : $parents), ...array_values($interfaces === false ? [] : $interfaces));
            }
        }

        return $found;
    }

    /**
     * @param Info $info
     * @return array<string, Info|null>
     */
    private function lineage(array $info): array
    {
        $lineage = [$info['name'] => $info];
        $queue = [...$info['parents'], ...$info['uses']];

        while ($queue !== []) {
            $name = mb_ltrim(array_shift($queue), '\\');

            if (array_key_exists($name, $lineage)) {
                continue;
            }

            $type = $this->types[$name] ?? null;
            $lineage[$name] = $type;

            if ($type !== null && $type['kind'] !== 'interface') {
                array_push($queue, ...$type['parents'], ...$type['uses']);
            }
        }

        return $lineage;
    }
}
