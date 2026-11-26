<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Localization\Services\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final readonly class SetApiLocale
{
    public const string HEADER = 'X-Locale';

    public function __construct(private LocaleService $locales)
    {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->locales->fromHeader($request, self::HEADER)
            ?? $this->locales->fromAcceptLanguage($request)
            ?? $this->locales->fallback();

        App::setLocale($locale->value);

        return $next($request);
    }
}
