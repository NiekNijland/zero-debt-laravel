<?php

declare(strict_types=1);

use App\Models\Note;
use App\Modules\Notes\Enums\NotePermission;
use Tests\Support\Users;

it('creates a note for the signed in user', function (): void {
    $user = Users::signedIn(NotePermission::CREATE);

    $this->postJson(route('notes.store'), ['title' => 'Hello', 'body' => 'World', 'status' => 'draft', 'pinned' => false])
        ->assertCreated()
        ->assertJsonPath('title', 'Hello')
        ->assertJsonPath('status', 'draft');

    expect(Note::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('refuses invalid input', function (): void {
    Users::signedIn(NotePermission::CREATE);

    $this->postJson(route('notes.store'), ['title' => '', 'status' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'body', 'status', 'pinned']);
});

it('refuses a user without the permission', function (): void {
    Users::signedIn();

    $this->postJson(route('notes.store'), ['title' => 'A', 'body' => 'B', 'status' => 'draft', 'pinned' => false])->assertForbidden();
});

it('refuses a guest', function (): void {
    $this->postJson(route('notes.store'))->assertUnauthorized();
});
