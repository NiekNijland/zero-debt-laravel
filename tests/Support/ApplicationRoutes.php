<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionFunction;

final class ApplicationRoutes
{
    /**
     * @return list<RoutingRoute>
     */
    public static function all(): array
    {
        return array_values(array_filter(Route::getRoutes()->getRoutes(), self::isOwned(...)));
    }

    /**
     * @param list<string> $methods
     */
    public static function forController(array $methods, string $uri, string $controller): RoutingRoute
    {
        return new RoutingRoute($methods, $uri, ['uses' => $controller . '@__invoke', 'controller' => $controller . '@__invoke']);
    }

    public static function isOwned(RoutingRoute $route): bool
    {
        $source = self::sourceFile($route);

        return $source !== null && (str_starts_with($source, base_path('app') . '/') || str_starts_with($source, base_path('routes') . '/'));
    }

    public static function identifier(RoutingRoute $route): string
    {
        $name = $route->getName();

        return $name === null || str_starts_with($name, 'generated::') ? $route->uri() : $name;
    }

    /**
     * @return list<string>
     */
    public static function middleware(RoutingRoute $route): array
    {
        return array_values(array_filter(Route::gatherRouteMiddleware($route), is_string(...)));
    }

    public static function area(RoutingRoute $route, string $controllers): ?string
    {
        $controller = $route->getControllerClass();

        if ($controller === null || ! str_starts_with($controller, $controllers . '\\')) {
            return null;
        }

        return explode('\\', mb_substr($controller, mb_strlen($controllers) + 1))[0];
    }

    private static function sourceFile(RoutingRoute $route): ?string
    {
        $uses = $route->getAction('uses');
        $controller = $route->getControllerClass();

        $file = match (true) {
            $uses instanceof Closure => new ReflectionFunction($uses)->getFileName(),
            $controller !== null && class_exists($controller) => new ReflectionClass($controller)->getFileName(),
            default => false,
        };

        return is_string($file) ? $file : null;
    }
}
