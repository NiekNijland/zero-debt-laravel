<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('takes the locale from the url first', function (): void {
    $this->withHeader('Accept-Language', 'en')->getJson(route('pages.show', ['locale' => 'nl', 'slug' => 'over']))->assertOk();

    expect(app()->getLocale())->toBe('nl');
});

it('uses the locale of the url for generated urls', function (): void {
    $this->getJson(route('pages.show', ['locale' => 'nl', 'slug' => 'over']))->assertOk();

    expect(route('pages.show', ['slug' => 'over']))->toContain('/nl/over');
});

it('falls back to the configured locale without a route or a header', function (): void {
    config(['app.locale' => 'nl']);

    $response = resolve(SetLocale::class)->handle(Request::create('/', server: ['HTTP_ACCEPT_LANGUAGE' => 'de']), static fn (): Response => new Response());

    expect($response->getStatusCode())->toBe(200)
        ->and(app()->getLocale())->toBe('nl');
});
