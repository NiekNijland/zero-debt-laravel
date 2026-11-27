<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
use Tests\Support\ApplicationRoutes;
use Tests\Support\Feature\Http\RoutePermissionCoverageHelpers;

it('puts a permission or a policy on the route of every route outside the public area', function (): void {
    expect(RoutePermissionCoverageHelpers::uncoveredRoutes('App\Http\Controllers', 'Web'))->toBe([]);
});

it('reads the middleware of a route, and a policy ability only when it names the model in the URL', function (): void {
    Gate::policy(User::class, ArrayObject::class);

    $index = static fn (string ...$middleware): RoutingRoute => ApplicationRoutes::forAction(['GET'], 'users', static fn (): string => '')->middleware($middleware);
    $show = static fn (string ...$middleware): RoutingRoute => ApplicationRoutes::forAction(['GET'], 'users/{user}', static fn (User $user): string => $user->name)->middleware($middleware);

    expect(RoutePermissionCoverageHelpers::isAuthenticated($index('api')))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::isAuthenticated($index('auth:sanctum')))->toBeTrue()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($index('auth:sanctum')))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($index('auth:sanctum', 'can:users.view')))->toBeTrue()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($show('auth:sanctum', 'can:users.view')))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($show('auth:sanctum', 'can:view,user')))->toBeTrue()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($show('auth:sanctum', 'can:view,other')))->toBeFalse()
        ->and(RoutePermissionCoverageHelpers::isAuthorized($show('auth:sanctum', 'can:view,user')->withoutMiddleware('can:view,user')))->toBeFalse();
});
