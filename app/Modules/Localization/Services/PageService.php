<?php

declare(strict_types=1);

namespace App\Modules\Localization\Services;

use App\Modules\Localization\Data\PageData;
use App\Modules\Localization\Enums\Locale;
use App\Modules\Localization\Enums\Page;
use App\Support\Translate;

final readonly class PageService
{
    public function slug(Page $page, Locale $locale): string
    {
        return match ($page) {
            Page::ABOUT => Translate::string('routes.about', locale: $locale->value),
        };
    }

    public function title(Page $page, Locale $locale): string
    {
        return match ($page) {
            Page::ABOUT => Translate::string('pages.about.title', locale: $locale->value),
        };
    }

    public function find(string $slug, Locale $locale): ?Page
    {
        return array_find(Page::cases(), fn (Page $page): bool => $this->slug($page, $locale) === $slug);
    }

    public function findInAnyLocale(string $slug): ?Page
    {
        foreach (Locale::cases() as $locale) {
            $page = $this->find($slug, $locale);

            if ($page instanceof Page) {
                return $page;
            }
        }

        return null;
    }

    public function data(Page $page, Locale $locale): PageData
    {
        return new PageData($page, $locale, $this->slug($page, $locale), $this->title($page, $locale));
    }
}
