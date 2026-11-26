<?php

declare(strict_types=1);

use App\Modules\Localization\Enums\Locale;
use App\Modules\Localization\Services\LocaleService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;

it('falls back to the configured locale', function (): void {
    config(['app.locale' => 'nl']);

    expect(resolve(LocaleService::class)->fallback())->toBe(Locale::NL);
});

it('reads the first supported language of the header', function (): void {
    $request = Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE,nl;q=0.8,en;q=0.5']);

    expect(resolve(LocaleService::class)->fromAcceptLanguage($request))->toBe(Locale::NL);
});

it('reads nothing from an unsupported header', function (): void {
    $request = Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE']);

    expect(resolve(LocaleService::class)->fromAcceptLanguage($request))->toBeNull();
});

it('reads a named header', function (): void {
    $request = Request::create('/', server: ['HTTP_X_LOCALE' => 'en']);

    expect(resolve(LocaleService::class)->fromHeader($request, 'X-Locale'))->toBe(Locale::EN)
        ->and(resolve(LocaleService::class)->fromHeader($request, 'X-Other'))->toBeNull();
});

it('reads the locale of the route as text or as an enum', function (): void {
    $locales = resolve(LocaleService::class);
    $request = Request::create('/nl/over');
    $route = new Route('GET', '{locale}/{slug}', static fn (): null => null);
    $route->bind($request);

    $request->setRouteResolver(static fn (): Route => $route);

    expect($locales->fromRoute($request))->toBe(Locale::NL);

    $route->setParameter('locale', Locale::EN);

    expect($locales->fromRoute($request))->toBe(Locale::EN);

    $route->setParameter('locale', 'xx');

    expect($locales->fromRoute($request))->toBeNull();
});

it('reads no locale from a request without a route', function (): void {
    expect(resolve(LocaleService::class)->fromRoute(Request::create('/')))->toBeNull();
});
