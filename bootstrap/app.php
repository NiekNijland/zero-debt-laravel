<?php

declare(strict_types=1);

use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\SetLocale;
use App\Support\Translate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        apiPrefix: 'api/v1',
        then: static function (): void {
            Route::middleware([SubstituteBindings::class, SetLocale::class, 'throttle:read'])->group(__DIR__ . '/../routes/web.php');
        },
    )
    ->withEvents(discover: [__DIR__ . '/../app/Modules/*/Listeners'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [SetApiLocale::class]);
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: SetApiLocale::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(static fn (): bool => true);
        $exceptions->map(static fn (AuthenticationException $exception): AuthenticationException => new AuthenticationException(Translate::string('Unauthenticated.')));
        $exceptions->map(static fn (AuthorizationException $exception): AuthorizationException => new AuthorizationException(Translate::string('This action is unauthorized.')));
    })
    ->create();
