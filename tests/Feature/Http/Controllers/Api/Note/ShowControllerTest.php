<?php

declare(strict_types=1);

use App\Models\Note;
use App\Modules\Notes\Enums\NotePermission;
use Tests\Support\Users;

it('shows the own note', function (): void {
    $user = Users::signedIn(NotePermission::VIEW);
    $note = Note::factory()->for($user)->create(['title' => 'Mine']);

    $this->getJson(route('notes.show', $note))->assertOk()->assertJsonPath('title', 'Mine')->assertJsonPath('id', $note->id);
});

it('refuses the note of another user', function (): void {
    Users::signedIn(NotePermission::VIEW);
    $note = Note::factory()->create();

    $this->getJson(route('notes.show', $note))->assertForbidden();
});

it('refuses a user without the permission', function (): void {
    $user = Users::signedIn();
    $note = Note::factory()->for($user)->create();

    $this->getJson(route('notes.show', $note))->assertForbidden();
});

it('answers not found for an unknown note', function (): void {
    Users::signedIn(NotePermission::VIEW);

    $this->getJson(route('notes.show', 999999))->assertNotFound();
});

it('refuses a guest', function (): void {
    $note = Note::factory()->create();

    $this->getJson(route('notes.show', $note))->assertUnauthorized();
});
