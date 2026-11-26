<?php

declare(strict_types=1);

use App\Modules\Notes\Enums\NotePermission;

it('names every permission under the notes prefix', function (): void {
    $values = array_map(static fn (NotePermission $permission): string => $permission->value, NotePermission::cases());

    expect($values)->toBe(['notes.view', 'notes.create', 'notes.update', 'notes.delete']);
});
