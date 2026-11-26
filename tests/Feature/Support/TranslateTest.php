<?php

declare(strict_types=1);

use App\Support\Translate;

it('translates a key into the given locale', function (): void {
    expect(Translate::string('pages.about.title', locale: 'nl'))->toBe('Over deze starter');
});

it('translates with the current locale', function (): void {
    app()->setLocale('en');

    expect(Translate::string('pages.about.title'))->toBe('About this starter');
});

it('returns the key for text that is no string', function (): void {
    expect(Translate::string('pages'))->toBe('pages');
});

it('replaces placeholders', function (): void {
    expect(Translate::string('validation.required', ['attribute' => 'title'], 'en'))->toBe('title is required.');
});
