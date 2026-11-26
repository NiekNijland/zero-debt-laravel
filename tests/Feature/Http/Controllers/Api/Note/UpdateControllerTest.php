<?php

declare(strict_types=1);

use App\Models\Note;
use App\Modules\Notes\Enums\NotePermission;
use Tests\Support\Users;

it('updates the own note', function (): void {
    $user = Users::signedIn(NotePermission::UPDATE);
    $note = Note::factory()->for($user)->create();

    $this->putJson(route('notes.update', $note), ['title' => 'Changed', 'body' => 'Text', 'status' => 'published', 'pinned' => true])
        ->assertOk()
        ->assertJsonPath('title', 'Changed')
        ->assertJsonPath('pinned', true);

    expect(Note::query()->findOrFail($note->id)->title)->toBe('Changed');
});

it('refuses invalid input', function (): void {
    $user = Users::signedIn(NotePermission::UPDATE);
    $note = Note::factory()->for($user)->create();

    $this->putJson(route('notes.update', $note), [])->assertUnprocessable();
});

it('refuses the note of another user and leaves it unchanged', function (): void {
    Users::signedIn(NotePermission::UPDATE);
    $note = Note::factory()->create(['title' => 'Theirs']);

    $this->putJson(route('notes.update', $note), ['title' => 'Mine', 'body' => 'x', 'status' => 'draft', 'pinned' => false])->assertForbidden();

    expect(Note::query()->findOrFail($note->id)->title)->toBe('Theirs');
});

it('refuses a guest', function (): void {
    $note = Note::factory()->create();

    $this->putJson(route('notes.update', $note))->assertUnauthorized();
});
