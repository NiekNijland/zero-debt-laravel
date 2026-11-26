<?php

declare(strict_types=1);

use App\Models\Note;
use App\Modules\Notes\Enums\NotePermission;
use Tests\Support\Users;

it('translates the validation message by the locale header', function (): void {
    Users::signedIn(NotePermission::CREATE);

    $this->withHeader('X-Locale', 'nl')->postJson(route('notes.store'), [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.title.0', 'titel is verplicht.');
});

it('falls back to the accept language header', function (): void {
    Users::signedIn(NotePermission::CREATE);

    $this->withHeader('Accept-Language', 'nl')->postJson(route('notes.store'), [])
        ->assertJsonPath('errors.title.0', 'titel is verplicht.');
});

it('falls back to the configured locale', function (): void {
    Users::signedIn(NotePermission::CREATE);

    $this->postJson(route('notes.store'), [])->assertJsonPath('errors.title.0', 'title is required.');
});

it('translates the unauthenticated message', function (): void {
    $this->withHeader('X-Locale', 'nl')->getJson(route('notes.index'))->assertUnauthorized()->assertJsonPath('message', 'Niet ingelogd.');
});

it('translates the unauthorized message', function (): void {
    $user = Users::signedIn(NotePermission::VIEW);
    $note = Note::factory()->create();

    $this->withHeader('X-Locale', 'nl')->getJson(route('notes.show', $note))->assertForbidden()->assertJsonPath('message', 'Deze actie is niet toegestaan.');
    expect($user->id)->toBeInt();
});
