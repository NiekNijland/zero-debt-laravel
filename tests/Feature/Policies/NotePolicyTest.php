<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\User;
use App\Modules\Notes\Enums\NotePermission;
use App\Policies\NotePolicy;
use Tests\Support\Users;

dataset('abilities', [
    'view' => [NotePermission::VIEW, static fn (NotePolicy $policy, User $user, Note $note): bool => $policy->view($user, $note)],
    'update' => [NotePermission::UPDATE, static fn (NotePolicy $policy, User $user, Note $note): bool => $policy->update($user, $note)],
    'delete' => [NotePermission::DELETE, static fn (NotePolicy $policy, User $user, Note $note): bool => $policy->delete($user, $note)],
]);

it('allows the owner with the permission', function (NotePermission $permission, Closure $ask): void {
    $user = Users::create($permission);
    $note = Note::factory()->for($user)->create();

    expect($ask(resolve(NotePolicy::class), $user, $note))->toBeTrue();
})->with('abilities');

it('refuses the owner without the permission', function (NotePermission $permission, Closure $ask): void {
    $user = Users::create();
    $note = Note::factory()->for($user)->create();

    expect($ask(resolve(NotePolicy::class), $user, $note))->toBeFalse();
})->with('abilities');

it('refuses another user even with the permission', function (NotePermission $permission, Closure $ask): void {
    $user = Users::create($permission);
    $note = Note::factory()->create();

    expect($ask(resolve(NotePolicy::class), $user, $note))->toBeFalse();
})->with('abilities');
