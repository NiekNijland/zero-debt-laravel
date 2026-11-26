<?php

declare(strict_types=1);

namespace Tests\Support\Feature\Http;

use Closure;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RoutingRoute;
use ReflectionNamedType;
use ReflectionParameter;
use Tests\Support\ApplicationRoutes;

final class ObjectLevelAccessHelpers
{
    /**
     * @return list<RoutingRoute>
     */
    public static function entryPoints(): array
    {
        return array_values(array_filter(ApplicationRoutes::all(), static fn (RoutingRoute $route): bool => self::recordParameters($route) !== []));
    }

    /**
     * @return array<string, class-string<UrlRoutable>>
     */
    public static function recordParameters(RoutingRoute $route): array
    {
        $parameters = [];

        foreach ($route->signatureParameters(['subClass' => UrlRoutable::class]) as $parameter) {
            if (! $parameter instanceof ReflectionParameter) {
                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && in_array($parameter->getName(), $route->parameterNames(), true) && is_a($type->getName(), UrlRoutable::class, true)) {
                $parameters[$parameter->getName()] = $type->getName();
            }
        }

        return $parameters;
    }

    /**
     * @param array<class-string, Closure(): Model> $factories
     * @return array<string, Model>|string
     */
    public static function foreignRecords(RoutingRoute $route, array $factories): array|string
    {
        $records = [];

        foreach (self::recordParameters($route) as $name => $class) {
            $factory = $factories[$class] ?? null;

            if ($factory === null) {
                return 'has no factory for ' . $class . ' in the map of foreign records';
            }

            $records[$name] = $factory();
        }

        return $records;
    }

    public static function method(RoutingRoute $route): string
    {
        return array_values(array_diff($route->methods(), ['HEAD']))[0] ?? 'GET';
    }
}
