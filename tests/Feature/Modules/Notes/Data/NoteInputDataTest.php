<?php

declare(strict_types=1);

use App\Modules\Notes\Data\NoteInputData;
use App\Modules\Notes\Enums\NoteStatus;

it('is built from validated input', function (): void {
    $data = NoteInputData::from(['title' => 'A', 'body' => 'B', 'status' => 'published', 'pinned' => true]);

    expect($data->title)->toBe('A')
        ->and($data->status)->toBe(NoteStatus::PUBLISHED)
        ->and($data->pinned)->toBeTrue();
});
