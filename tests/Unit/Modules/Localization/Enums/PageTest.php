<?php

declare(strict_types=1);

use App\Modules\Localization\Enums\Page;

it('has the about page', function (): void {
    expect(Page::from('about'))->toBe(Page::ABOUT);
});
