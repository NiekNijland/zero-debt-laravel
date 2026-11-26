<?php

declare(strict_types=1);

use App\Modules\Localization\Enums\Locale;
use App\Modules\Localization\Enums\Page;
use App\Modules\Localization\Services\PageService;

it('names the slug and title per locale', function (): void {
    $pages = resolve(PageService::class);

    expect($pages->slug(Page::ABOUT, Locale::NL))->toBe('over')
        ->and($pages->slug(Page::ABOUT, Locale::EN))->toBe('about')
        ->and($pages->title(Page::ABOUT, Locale::NL))->toBe('Over deze starter');
});

it('finds a page by the slug of one locale', function (): void {
    $pages = resolve(PageService::class);

    expect($pages->find('over', Locale::NL))->toBe(Page::ABOUT)
        ->and($pages->find('about', Locale::NL))->toBeNull();
});

it('finds a page by the slug of any locale', function (): void {
    $pages = resolve(PageService::class);

    expect($pages->findInAnyLocale('over'))->toBe(Page::ABOUT)
        ->and($pages->findInAnyLocale('about'))->toBe(Page::ABOUT)
        ->and($pages->findInAnyLocale('missing'))->toBeNull();
});

it('builds the page data', function (): void {
    $data = resolve(PageService::class)->data(Page::ABOUT, Locale::NL);

    expect($data->slug)->toBe('over')
        ->and($data->title)->toBe('Over deze starter')
        ->and($data->locale)->toBe(Locale::NL);
});
