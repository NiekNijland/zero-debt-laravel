<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::shouldBeStrict();

        if ($this->app->isProduction()) {
            Model::handleLazyLoadingViolationUsing(static function (Model $model, string $relation): void {
                report(new LazyLoadingViolationException($model, $relation));
            });
        }

        DB::prohibitDestructiveCommands($this->app->isProduction());
        URL::forceHttps($this->app->isProduction());
        Date::use(CarbonImmutable::class);
        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->uncompromised()
            : Password::min(12)->mixedCase()->numbers());
    }
}
