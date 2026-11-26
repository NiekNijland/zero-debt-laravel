<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Note\IndexController;
use App\Http\Controllers\Api\Note\ShowController;
use Illuminate\Routing\Route as RoutingRoute;
use Tests\Support\ApplicationRoutes;
use Tests\Support\Feature\Http\RoutePermissionCoverageHelpers;

it('puts a permission or a policy on the route of every route outside the public area', function (): void {
    expect(RoutePermissionCoverageHelpers::uncoveredRoutes('App\Http\Controllers', 'Web'))->toBe([]);
});

it('reads the middleware of a route, and a policy ability only when it names the model in the URL', function (): void {
    $index = static fn (string ...$middleware): RoutingRoute => ApplicationRoutes::forController(['GET'], 'notes', IndexController::class)->middleware($middleware);
    $show = static fn (string ...$middleware): RoutingRoute => ApplicationRoutes::forController(['GET'], 'notes/{note}', ShowController::class)->middleware($middleware);

    expect(RoutePermissionCoverageHelpers::isAuthenticated($index('api')))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::isAuthenticated($index('auth:sanctum')))->toBeTrue()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($index('auth:sanctum')))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($index('auth:sanctum', 'can:notes.view')))->toBeTrue()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($show('auth:sanctum', 'can:notes.view')))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($show('auth:sanctum', 'can:view,note')))->toBeTrue()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($show('auth:sanctum', 'can:view,other')))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($show('auth:sanctum', 'can:view,note')->withoutMiddleware('can:view,note')))->toBeFalse();
});
