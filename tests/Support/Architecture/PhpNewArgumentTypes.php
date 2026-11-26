<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

final class PhpNewArgumentTypes
{
    /**
     * @return list<array{line: int, type: string|null}>
     */
    public static function firstArgumentTypesOfNew(string $file, string $class): array
    {
        $finder = new NodeFinder();
        $found = [];

        foreach (PhpSource::statements($file) as $statement) {
            foreach ($finder->findInstanceOf($statement, Class_::class) as $declaration) {
                foreach ($declaration->getMethods() as $method) {
                    foreach ($finder->findInstanceOf($method, New_::class) as $new) {
                        $first = $new->args[0] ?? null;

                        if (! $new->class instanceof Name || mb_ltrim($new->class->toString(), '\\') !== $class || ! $first instanceof Arg || ($first->name instanceof Identifier && $first->name->toString() !== 'data')) {
                            continue;
                        }

                        $found[] = ['line' => $new->getStartLine(), 'type' => self::typeOf($first->value, $declaration, $method)];
                    }
                }
            }
        }

        return $found;
    }

    private static function typeOf(Expr $expression, Class_ $class, ClassMethod $method): ?string
    {
        if ($expression instanceof Array_) {
            return 'array';
        }

        if (($expression instanceof New_ || $expression instanceof StaticCall) && $expression->class instanceof Name) {
            $name = mb_ltrim($expression->class->toString(), '\\');
            $name = in_array($name, ['self', 'static'], true) ? $class->namespacedName?->toString() : $name;

            if ($expression instanceof New_ || $name === null) {
                return $name;
            }

            if (! $expression->name instanceof Identifier || ! class_exists($name) || ! method_exists($name, $expression->name->toString())) {
                return null;
            }

            if ($expression->name->toString() === 'collect') {
                $into = $expression->args[1] ?? null;

                if ($into instanceof Arg && $into->value instanceof ClassConstFetch && $into->value->class instanceof Name) {
                    return mb_ltrim($into->value->class->toString(), '\\');
                }
            }

            $declared = self::reflectedType(self::publicReturnType($name, $expression->name->toString()), $name);

            return $declared === 'mixed' ? null : $declared;
        }

        if ($expression instanceof Variable && is_string($expression->name)) {
            return self::typeOfVariable($expression, $expression->name, $class, $method);
        }

        if ($expression instanceof MethodCall && $expression->name instanceof Identifier) {
            return self::typeOfCall($expression, $class, $method);
        }

        return null;
    }

    private static function typeOfVariable(Variable $use, string $name, Class_ $class, ClassMethod $method): ?string
    {
        $assignments = new NodeFinder()->find($method, static fn (Node $node): bool => $node instanceof Assign
            && $node->var instanceof Variable
            && $node->var->name === $name
            && $node->getStartTokenPos() < $use->getStartTokenPos());
        $last = end($assignments);

        if ($last instanceof Assign) {
            return self::typeOf($last->expr, $class, $method);
        }

        return self::parameterType($method, $name);
    }

    private static function typeOfCall(MethodCall $call, Class_ $class, ClassMethod $method): ?string
    {
        $receiver = $call->var;
        $receiverType = match (true) {
            $receiver instanceof PropertyFetch && $receiver->var instanceof Variable && $receiver->var->name === 'this' && $receiver->name instanceof Identifier => self::propertyType($class, $receiver->name->toString()),
            $receiver instanceof Variable && is_string($receiver->name) => self::parameterType($method, $receiver->name),
            default => null,
        };

        if ($receiverType === null || ! class_exists($receiverType) || ! $call->name instanceof Identifier || ! method_exists($receiverType, $call->name->toString())) {
            return null;
        }

        $declared = self::reflectedType(self::publicReturnType($receiverType, $call->name->toString()), $receiverType);

        if ($declared !== 'mixed') {
            return $declared;
        }

        foreach ($call->args as $argument) {
            if ($argument instanceof Arg && ($argument->value instanceof Closure || $argument->value instanceof ArrowFunction)) {
                return self::nodeType($argument->value->returnType);
            }
        }

        return null;
    }

    private static function parameterType(ClassMethod $method, string $name): ?string
    {
        foreach ($method->params as $parameter) {
            if ($parameter->var instanceof Variable && $parameter->var->name === $name) {
                return self::nodeType($parameter->type);
            }
        }

        return null;
    }

    private static function propertyType(Class_ $class, string $name): ?string
    {
        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $item) {
                if ($item->name->toString() === $name) {
                    return self::nodeType($property->type);
                }
            }
        }

        $constructor = $class->getMethod('__construct');

        foreach ($constructor->params ?? [] as $parameter) {
            if ($parameter->flags !== 0 && $parameter->var instanceof Variable && $parameter->var->name === $name) {
                return self::nodeType($parameter->type);
            }
        }

        return null;
    }

    private static function nodeType(?Node $type): ?string
    {
        if ($type instanceof NullableType) {
            return self::nodeType($type->type);
        }

        if ($type instanceof Name) {
            return mb_ltrim($type->toString(), '\\');
        }

        if ($type instanceof Identifier) {
            return $type->toString();
        }

        if ($type instanceof UnionType) {
            $parts = array_map(self::nodeType(...), $type->types);

            return in_array(null, $parts, true) ? null : implode('|', $parts);
        }

        return null;
    }

    /**
     * @param class-string $class
     */
    private static function publicReturnType(string $class, string $method): ?ReflectionType
    {
        foreach (new ReflectionClass($class)->getMethods(ReflectionMethod::IS_PUBLIC) as $candidate) {
            if (strcasecmp($candidate->getName(), $method) === 0) {
                return $candidate->getReturnType();
            }
        }

        return null;
    }

    private static function reflectedType(?ReflectionType $type, string $class): ?string
    {
        if ($type instanceof ReflectionNamedType) {
            return in_array($type->getName(), ['self', 'static'], true) ? $class : $type->getName();
        }

        if ($type instanceof ReflectionUnionType) {
            $parts = array_map(fn (ReflectionType $part): ?string => self::reflectedType($part, $class), $type->getTypes());

            return in_array(null, $parts, true) ? null : implode('|', $parts);
        }

        return null;
    }
}
