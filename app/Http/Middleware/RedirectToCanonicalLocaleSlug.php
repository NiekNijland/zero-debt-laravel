<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Localization\Enums\Locale;
use App\Modules\Localization\Enums\Page;
use App\Modules\Localization\Services\LocaleService;
use App\Modules\Localization\Services\PageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RedirectToCanonicalLocaleSlug
{
    public function __construct(
        private LocaleService $locales,
        private PageService $pages,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->locales->fromRoute($request);
        $slug = $request->route('slug');
        $page = is_string($slug) ? $this->pages->findInAnyLocale($slug) : null;

        if (! $request->isMethodSafe() || ! $locale instanceof Locale || ! $page instanceof Page) {
            return $next($request);
        }

        $canonical = $this->pages->slug($page, $locale);

        if ($canonical === $slug) {
            return $next($request);
        }

        return to_route('pages.show', [...$request->query(), 'locale' => $locale->value, 'slug' => $canonical], 301);
    }
}
