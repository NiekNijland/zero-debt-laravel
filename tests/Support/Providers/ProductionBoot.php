<?php

declare(strict_types=1);

namespace Tests\Support\Providers;

use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

final class ProductionBoot
{
    public static function run(): void
    {
        $app = app();
        $app->detectEnvironment(static fn (): string => 'production');

        new AppServiceProvider($app)->boot();
    }

    public static function reset(): void
    {
        DB::prohibitDestructiveCommands(false);
        URL::forceHttps(false);
        Model::handleLazyLoadingViolationUsing(null);
    }
}
