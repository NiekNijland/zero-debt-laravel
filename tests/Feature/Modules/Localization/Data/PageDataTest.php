<?php

declare(strict_types=1);

use App\Modules\Localization\Data\PageData;
use App\Modules\Localization\Enums\Locale;
use App\Modules\Localization\Enums\Page;

it('writes its fields', function (): void {
    $data = new PageData(Page::ABOUT, Locale::EN, 'about', 'About');

    expect($data->toArray())->toBe(['page' => 'about', 'locale' => 'en', 'slug' => 'about', 'title' => 'About']);
});
