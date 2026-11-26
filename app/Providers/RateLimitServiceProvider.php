<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('read', static fn (Request $request): Limit => Limit::perMinute(Config::integer('rate-limits.read'))
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('write', static fn (Request $request): Limit => Limit::perMinute(Config::integer('rate-limits.write'))
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
