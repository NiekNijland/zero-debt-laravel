<?php

declare(strict_types=1);

namespace Tests\Support\Providers;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use LogicException;

final class Limiters
{
    public static function limit(string $name, Request $request): Limit
    {
        $limiter = RateLimiter::limiter($name);

        throw_unless($limiter instanceof Closure, LogicException::class, "No limiter named {$name}.");

        $limit = $limiter($request);

        return $limit instanceof Limit ? $limit : throw new LogicException("The limiter {$name} gave no limit.");
    }
}
