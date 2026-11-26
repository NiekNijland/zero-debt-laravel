<?php

declare(strict_types=1);

use App\Models\Note;
use App\Modules\Notes\Enums\NotePermission;
use Tests\Support\Users;

it('lists only the notes of the signed in user, paginated', function (): void {
    $user = Users::signedIn(NotePermission::VIEW);
    Note::factory()->for($user)->count(3)->create();
    Note::factory()->create();

    $this->getJson(route('notes.index', ['per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2);
});

it('uses the configured page size', function (): void {
    Users::signedIn(NotePermission::VIEW);
    config(['notes.per_page' => 4]);

    $this->getJson(route('notes.index'))->assertOk()->assertJsonPath('meta.per_page', 4);
});

it('refuses a page size above the maximum', function (): void {
    Users::signedIn(NotePermission::VIEW);

    $this->getJson(route('notes.index', ['per_page' => 51]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

it('refuses a user without the permission', function (): void {
    Users::signedIn();

    $this->getJson(route('notes.index'))->assertForbidden();
});

it('refuses a guest', function (): void {
    $this->getJson(route('notes.index'))->assertUnauthorized();
});
