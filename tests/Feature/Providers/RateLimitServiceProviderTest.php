<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Tests\Support\Providers\Limiters;
use Tests\Support\Users;

it('registers a read and a write limiter with the numbers of the config', function (): void {
    config(['rate-limits.read' => 11, 'rate-limits.write' => 3]);
    $request = Request::create('/');

    $read = Limiters::limit('read', $request);
    $write = Limiters::limit('write', $request);

    expect($read)->toBeInstanceOf(Limit::class)
        ->and($read->maxAttempts)->toBe(11)
        ->and($write->maxAttempts)->toBe(3);
});

it('keys the limit by user and falls back to the ip', function (): void {
    $guest = Limiters::limit('read', Request::create('/', server: ['REMOTE_ADDR' => '10.0.0.1']));
    $user = Users::create();
    $request = Request::create('/');
    $request->setUserResolver(static fn (): Authenticatable => $user);

    $signedIn = Limiters::limit('read', $request);

    expect($guest->key)->toBe('10.0.0.1')
        ->and($signedIn->key)->toBe($user->id);
});

it('answers too many requests above the limit', function (): void {
    config(['rate-limits.write' => 1]);
    Route::prefix('api/v1')->middleware(['api', 'auth:sanctum', 'throttle:write'])->delete('probe', static fn (): Response => response()->noContent())->name('probe.destroy');
    Route::getRoutes()->refreshNameLookups();
    Users::signedIn();

    $this->deleteJson(route('probe.destroy'))->assertNoContent();
    $this->deleteJson(route('probe.destroy'))->assertTooManyRequests();
});
