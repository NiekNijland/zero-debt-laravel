<?php

declare(strict_types=1);

use App\Modules\Localization\Enums\Locale;
use Tests\Support\Translations\TranslationKeys;

it('has every key in every language', function (): void {
    $english = array_keys(TranslationKeys::defined('en'));
    $dutch = array_keys(TranslationKeys::defined('nl'));

    expect(array_values(array_diff($english, $dutch)))->toBe([])
        ->and(array_values(array_diff($dutch, $english)))->toBe([]);
});

it('has a language file for every locale', function (): void {
    foreach (Locale::cases() as $locale) {
        expect(TranslationKeys::defined($locale->value))->not->toBe([]);
    }
});

it('keeps the placeholders of every key the same in every language', function (): void {
    $english = TranslationKeys::defined('en');
    $dutch = TranslationKeys::defined('nl');
    $mismatches = [];

    foreach ($english as $key => $text) {
        if (TranslationKeys::placeholders($text) !== TranslationKeys::placeholders($dutch[$key] ?? '')) {
            $mismatches[] = $key;
        }
    }

    expect($mismatches)->toBe([]);
});

it('defines every key the code uses', function (): void {
    expect(array_values(array_diff(TranslationKeys::used(), array_keys(TranslationKeys::defined('en')))))->toBe([]);
});

it('uses every key it defines, apart from the files the framework reads', function (): void {
    $used = TranslationKeys::used();
    $unused = array_filter(
        array_keys(TranslationKeys::defined('en')),
        static fn (string $key): bool => ! in_array(explode('.', $key)[0], TranslationKeys::FRAMEWORK_GROUPS, true)
            && ! in_array($key, $used, true),
    );

    expect(array_values($unused))->toBe([]);
});

it('has no empty text', function (): void {
    foreach (['en', 'nl'] as $locale) {
        expect(array_keys(array_filter(TranslationKeys::defined($locale), static fn (string $text): bool => $text === '')))->toBe([]);
    }
});
