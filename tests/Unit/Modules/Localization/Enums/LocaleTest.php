<?php

declare(strict_types=1);

use App\Modules\Localization\Enums\Locale;

it('has Dutch and English', function (): void {
    expect(array_map(static fn (Locale $locale): string => $locale->value, Locale::cases()))->toBe(['nl', 'en']);
});

it('refuses an unknown locale', function (): void {
    expect(Locale::tryFrom('de'))->toBeNull();
});
