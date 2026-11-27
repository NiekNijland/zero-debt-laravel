<?php

declare(strict_types=1);

use Tests\Support\ApplicationRoutes;
use Tests\Support\Feature\Http\ObjectLevelAccessHelpers;

it('has the five routes of the notes, three of them with a record id, so the route coverage tests read something', function (): void {
    $names = array_map(ApplicationRoutes::identifier(...), ApplicationRoutes::all());
    $records = array_map(ApplicationRoutes::identifier(...), ObjectLevelAccessHelpers::entryPoints());

    expect($names)->toContain('notes.index', 'notes.store', 'notes.show', 'notes.update', 'notes.destroy', 'pages.show')
        ->and($records)->toEqualCanonicalizing(['notes.show', 'notes.update', 'notes.destroy']);
});
