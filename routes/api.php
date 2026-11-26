<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Note;
use App\Modules\Notes\Enums\NotePermission;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('notes', Note\IndexController::class)
        ->middleware(['can:' . NotePermission::VIEW->value, 'throttle:read'])
        ->name('notes.index');
    Route::post('notes', Note\StoreController::class)
        ->middleware(['can:' . NotePermission::CREATE->value, 'throttle:write'])
        ->name('notes.store');
    Route::get('notes/{note}', Note\ShowController::class)
        ->middleware(['can:view,note', 'throttle:read'])
        ->name('notes.show');
    Route::put('notes/{note}', Note\UpdateController::class)
        ->middleware(['can:update,note', 'throttle:write'])
        ->name('notes.update');
    Route::delete('notes/{note}', Note\DestroyController::class)
        ->middleware(['can:delete,note', 'throttle:write'])
        ->name('notes.destroy');
});
