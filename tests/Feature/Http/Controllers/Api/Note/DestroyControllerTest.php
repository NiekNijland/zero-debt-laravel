<?php

declare(strict_types=1);

use App\Models\Note;
use App\Modules\Notes\Enums\NotePermission;
use Tests\Support\Users;

it('deletes the own note', function (): void {
    $user = Users::signedIn(NotePermission::DELETE);
    $note = Note::factory()->for($user)->create();

    $this->deleteJson(route('notes.destroy', $note))->assertNoContent();

    expect(Note::query()->count())->toBe(0);
});

it('refuses the note of another user and keeps it', function (): void {
    Users::signedIn(NotePermission::DELETE);
    $note = Note::factory()->create();

    $this->deleteJson(route('notes.destroy', $note))->assertForbidden();

    expect(Note::query()->count())->toBe(1);
});

it('refuses a guest', function (): void {
    $note = Note::factory()->create();

    $this->deleteJson(route('notes.destroy', $note))->assertUnauthorized();
});
