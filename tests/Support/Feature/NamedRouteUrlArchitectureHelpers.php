<?php

declare(strict_types=1);

namespace Tests\Support\Feature;

use Illuminate\Support\Facades\Route;

final class NamedRouteUrlArchitectureHelpers
{
    /**
     * @return list<array{methods: list<string>, uri: string, name: string}>
     */
    public static function sampleRoutes(): array
    {
        return [
            ['methods' => ['GET', 'HEAD'], 'uri' => 'login', 'name' => 'login'],
            ['methods' => ['POST'], 'uri' => 'logout', 'name' => 'logout'],
            ['methods' => ['GET', 'HEAD'], 'uri' => 'orders/{order}', 'name' => 'orders.show'],
            ['methods' => ['PUT'], 'uri' => 'admin/suppliers/{supplier}/contacts', 'name' => 'admin.suppliers.contacts.update'],
            ['methods' => ['GET', 'HEAD'], 'uri' => 'invoices/export', 'name' => 'invoices.export'],
        ];
    }

    /**
     * @return list<array{methods: list<string>, uri: string, name: string}>
     */
    public static function namedRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || str_starts_with($name, 'generated::') || $route->getDomain() !== null) {
                continue;
            }

            $routes[] = ['methods' => array_values($route->methods()), 'uri' => $route->uri(), 'name' => $name];
        }

        return $routes;
    }
}
