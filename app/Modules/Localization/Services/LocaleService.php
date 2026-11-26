<?php

declare(strict_types=1);

namespace App\Modules\Localization\Services;

use App\Modules\Localization\Enums\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

final readonly class LocaleService
{
    public function fallback(): Locale
    {
        return Locale::from(Config::string('app.locale'));
    }

    public function fromAcceptLanguage(Request $request): ?Locale
    {
        foreach ($request->getLanguages() as $language) {
            $locale = Locale::tryFrom(mb_strtolower(explode('_', $language)[0]));

            if ($locale instanceof Locale) {
                return $locale;
            }
        }

        return null;
    }

    public function fromHeader(Request $request, string $header): ?Locale
    {
        $value = $request->header($header);

        return is_string($value) ? Locale::tryFrom($value) : null;
    }

    public function fromRoute(Request $request): ?Locale
    {
        $value = $request->route('locale');

        if ($value instanceof Locale) {
            return $value;
        }

        return is_string($value) ? Locale::tryFrom($value) : null;
    }
}
