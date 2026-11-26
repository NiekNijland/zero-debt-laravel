<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Page;
use App\Http\Middleware\RedirectToCanonicalLocaleSlug;
use Illuminate\Support\Facades\Route;

Route::get('{locale}/{slug}', Page\ShowController::class)
    ->middleware(RedirectToCanonicalLocaleSlug::class)
    ->name('pages.show');
