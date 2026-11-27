<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\Support\Users;

beforeEach(function (): void {
    Route::prefix('api/v1')->middleware(['api', 'auth:sanctum'])->group(static function (): void {
        Route::post('probe', static function (Request $request): string {
            $request->validate(['title' => ['required']]);

            return 'ok';
        })->name('probe.store');
        Route::get('probe', static fn (): string => 'ok')->middleware('can:probe.view')->name('probe.show');
    });
    Route::getRoutes()->refreshNameLookups();
});

it('translates the validation message by the locale header', function (): void {
    Users::signedIn();

    $this->withHeader('X-Locale', 'nl')->postJson(route('probe.store'), [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.title.0', 'titel is verplicht.');
});

it('falls back to the accept language header', function (): void {
    Users::signedIn();

    $this->withHeader('Accept-Language', 'nl')->postJson(route('probe.store'), [])
        ->assertJsonPath('errors.title.0', 'titel is verplicht.');
});

it('falls back to the configured locale', function (): void {
    Users::signedIn();

    $this->postJson(route('probe.store'), [])->assertJsonPath('errors.title.0', 'title is required.');
});

it('translates the unauthenticated message', function (): void {
    $this->withHeader('X-Locale', 'nl')->getJson(route('probe.show'))->assertUnauthorized()->assertJsonPath('message', 'Niet ingelogd.');
});

it('translates the unauthorized message', function (): void {
    Users::signedIn();

    $this->withHeader('X-Locale', 'nl')->getJson(route('probe.show'))->assertForbidden()->assertJsonPath('message', 'Deze actie is niet toegestaan.');
});
