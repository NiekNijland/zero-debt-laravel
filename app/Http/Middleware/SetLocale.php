<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Localization\Services\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

final readonly class SetLocale
{
    public function __construct(private LocaleService $locales)
    {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->locales->fromRoute($request)
            ?? $this->locales->fromAcceptLanguage($request)
            ?? $this->locales->fallback();

        App::setLocale($locale->value);
        URL::defaults(['locale' => $locale->value]);

        return $next($request);
    }
}
