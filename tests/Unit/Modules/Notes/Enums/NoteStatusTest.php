<?php

declare(strict_types=1);

use App\Modules\Notes\Enums\NoteStatus;

it('is a string backed enum with the stored values', function (): void {
    expect(array_map(static fn (NoteStatus $status): string => $status->value, NoteStatus::cases()))->toBe(['draft', 'published']);
});

it('is built from its stored value', function (): void {
    expect(NoteStatus::from('published'))->toBe(NoteStatus::PUBLISHED);
});
