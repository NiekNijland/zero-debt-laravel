<?php

declare(strict_types=1);

namespace App\Modules\Localization\Data;

use App\Modules\Localization\Enums\Locale;
use App\Modules\Localization\Enums\Page;
use Spatie\LaravelData\Data;

final class PageData extends Data
{
    public function __construct(
        public readonly Page $page,
        public readonly Locale $locale,
        public readonly string $slug,
        public readonly string $title,
    ) {
    }
}
