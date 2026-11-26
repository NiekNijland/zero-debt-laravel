<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Note\IndexController;
use App\Http\Controllers\Api\Note\StoreController;
use Illuminate\Routing\Route as RoutingRoute;
use Tests\Support\ApplicationRoutes;
use Tests\Support\Feature\Http\RouteRateLimitCoverageHelpers;

it('puts every route of the application behind one registered limiter, the write limiter for a write', function (): void {
    expect(RouteRateLimitCoverageHelpers::problems(['write']))->toBe([]);
});

it('reports a route that slips through without a named limiter', function (): void {
    $read = static fn (string ...$middleware): RoutingRoute => ApplicationRoutes::forController(['GET', 'HEAD'], 'notes', IndexController::class)->middleware($middleware);
    $write = static fn (string ...$middleware): RoutingRoute => ApplicationRoutes::forController(['POST'], 'notes', StoreController::class)->middleware($middleware);

    expect(RouteRateLimitCoverageHelpers::problem($read(), ['write']))->toBe('has no throttle:<name> limiter')
        ->and(RouteRateLimitCoverageHelpers::problem($read('throttle:60,1'), ['write']))->toBe('uses 60,1, which is not a limiter registered in the rate-limit provider')
        ->and(RouteRateLimitCoverageHelpers::problem($read('throttle'), ['write']))->toBe('uses a throttle without a limiter name, which is not a limiter registered in the rate-limit provider')
        ->and(RouteRateLimitCoverageHelpers::problem($read('throttle:read', 'throttle:write'), ['write']))->toBe('counts in more than one limiter: read, write')
        ->and(RouteRateLimitCoverageHelpers::problem($read('throttle:write'), ['write']))->toBe('reads with the write limiter write')
        ->and(RouteRateLimitCoverageHelpers::problem($write('throttle:read'), ['write']))->toBe('writes with the read limiter read')
        ->and(RouteRateLimitCoverageHelpers::problem($read('throttle:read'), ['write']))->toBeNull()
        ->and(RouteRateLimitCoverageHelpers::problem($write('throttle:write'), ['write']))->toBeNull();
});
