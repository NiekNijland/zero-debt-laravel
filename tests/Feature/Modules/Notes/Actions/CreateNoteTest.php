<?php

declare(strict_types=1);

use App\Models\Note;
use App\Modules\Notes\Actions\CreateNote;
use App\Modules\Notes\Data\NoteInputData;
use App\Modules\Notes\Enums\NoteStatus;
use Tests\Support\Users;

it('stores a note for the user', function (): void {
    $user = Users::create();

    $note = resolve(CreateNote::class)->handle($user, new NoteInputData('Title', 'Body', NoteStatus::PUBLISHED, true));

    expect($note->user_id)->toBe($user->id)
        ->and($note->status)->toBe(NoteStatus::PUBLISHED)
        ->and($note->pinned)->toBeTrue()
        ->and(Note::query()->count())->toBe(1);
});
