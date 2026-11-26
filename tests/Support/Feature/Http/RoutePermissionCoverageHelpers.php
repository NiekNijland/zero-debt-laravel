<?php

declare(strict_types=1);

namespace Tests\Support\Feature\Http;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use ReflectionNamedType;
use ReflectionParameter;
use Tests\Support\ApplicationRoutes;

final class RoutePermissionCoverageHelpers
{
    /**
     * @return list<string>
     */
    public static function uncoveredRoutes(string $controllers, string $publicArea): array
    {
        return array_values(array_unique(array_map(
            ApplicationRoutes::identifier(...),
            array_filter(
                ApplicationRoutes::all(),
                static fn (RoutingRoute $route): bool => ApplicationRoutes::area($route, $controllers) !== $publicArea
                    && (! self::isAuthenticated($route) || ! self::isAuthorized($route)),
            ),
        )));
    }

    public static function isAuthenticated(RoutingRoute $route): bool
    {
        return array_any(
            ApplicationRoutes::middleware($route),
            static fn (string $middleware): bool => Str::before($middleware, ':') === Authenticate::class,
        );
    }

    public static function isAuthorized(RoutingRoute $route): bool
    {
        $checks = self::checks($route);
        $models = self::policyModels($route);

        if ($models === []) {
            return $checks !== [];
        }

        return array_any($models, static fn (string $model): bool => array_any(
            $checks,
            static fn (string $check): bool => in_array($model, array_slice(explode(',', Str::after($check, ':')), 1), true),
        ));
    }

    /**
     * @return list<string>
     */
    private static function checks(RoutingRoute $route): array
    {
        return array_values(array_filter(
            ApplicationRoutes::middleware($route),
            static fn (string $middleware): bool => str_contains($middleware, ':')
                && Str::before($middleware, ':') === Authorize::class,
        ));
    }

    /**
     * @return list<string>
     */
    private static function policyModels(RoutingRoute $route): array
    {
        $models = [];

        foreach ($route->signatureParameters(['subClass' => UrlRoutable::class]) as $parameter) {
            if ($parameter instanceof ReflectionParameter && self::bindsPolicyModel($parameter, $route)) {
                $models[] = $parameter->getName();
            }
        }

        return $models;
    }

    private static function bindsPolicyModel(ReflectionParameter $parameter, RoutingRoute $route): bool
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType
            && in_array($parameter->getName(), $route->parameterNames(), true)
            && Gate::getPolicyFor($type->getName()) !== null;
    }
}
