<?php

declare(strict_types=1);

use App\Models\Note;
use App\Modules\Notes\Data\NoteData;
use App\Modules\Notes\Enums\NoteStatus;

it('is built from a note and written with snake case keys', function (): void {
    $created = Note::factory()->create(['title' => 'Hi']);
    $note = Note::query()->findOrFail($created->id);

    $data = NoteData::fromModel($note);

    expect($data->id)->toBe($note->id)
        ->and($data->status)->toBe(NoteStatus::DRAFT)
        ->and($data->toArray())->toHaveKeys(['id', 'title', 'body', 'status', 'pinned', 'created_at', 'updated_at'])
        ->and($data->toArray()['title'])->toBe('Hi');
});
