<?php

declare(strict_types=1);

use App\Models\Note;
use App\Modules\Notes\Actions\DeleteNote;

it('removes the note', function (): void {
    $note = Note::factory()->create();

    resolve(DeleteNote::class)->handle($note);

    expect(Note::query()->count())->toBe(0);
});
