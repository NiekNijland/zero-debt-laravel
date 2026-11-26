<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\User;
use App\Modules\Notes\Services\NoteService;

it('pages only the notes of the user, pinned first and newest next', function (): void {
    $user = User::factory()->create();
    $plain = Note::factory()->for($user)->create();
    $pinned = Note::factory()->for($user)->create(['pinned' => true]);
    $newest = Note::factory()->for($user)->create();
    Note::factory()->create();

    $page = resolve(NoteService::class)->paginateFor($user, 2);

    expect($page->total())->toBe(3)
        ->and($page->perPage())->toBe(2)
        ->and(array_map(static fn (Note $note): int => $note->id, $page->items()))->toBe([$pinned->id, $newest->id])
        ->and($plain->id)->not->toBe($newest->id);
});
