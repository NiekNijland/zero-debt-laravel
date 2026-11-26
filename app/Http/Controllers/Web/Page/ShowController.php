<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Page;

use App\Modules\Localization\Data\PageData;
use App\Modules\Localization\Enums\Locale;
use App\Modules\Localization\Services\PageService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class ShowController
{
    public function __invoke(Locale $locale, string $slug, PageService $pages): PageData
    {
        $page = $pages->find($slug, $locale) ?? throw new NotFoundHttpException();

        return $pages->data($page, $locale);
    }
}
