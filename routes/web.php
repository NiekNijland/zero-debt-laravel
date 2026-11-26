<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Page\ShowController;
use App\Http\Middleware\RedirectToCanonicalLocaleSlug;
use Illuminate\Support\Facades\Route;

Route::get('{locale}/{slug}', ShowController::class)
    ->middleware(RedirectToCanonicalLocaleSlug::class)
    ->name('pages.show');
