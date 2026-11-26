<?php

declare(strict_types=1);

use App\Models\Note;
use App\Modules\Notes\Actions\UpdateNote;
use App\Modules\Notes\Data\NoteInputData;
use App\Modules\Notes\Enums\NoteStatus;

it('changes every field of the note', function (): void {
    $created = Note::factory()->create();

    $note = resolve(UpdateNote::class)->handle($created, new NoteInputData('New', 'Text', NoteStatus::PUBLISHED, true));

    expect($note->title)->toBe('New')
        ->and($note->body)->toBe('Text')
        ->and($note->status)->toBe(NoteStatus::PUBLISHED)
        ->and($note->pinned)->toBeTrue();
});
