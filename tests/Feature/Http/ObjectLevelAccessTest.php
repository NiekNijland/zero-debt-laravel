<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Note\IndexController;
use App\Http\Controllers\Api\Note\ShowController;
use App\Models\Note;
use App\Modules\Notes\Enums\NotePermission;
use Tests\Support\ApplicationRoutes;
use Tests\Support\Feature\Http\ObjectLevelAccessHelpers;
use Tests\Support\Users;

it('refuses every route with a record id for a record that belongs to someone else', function (): void {
    $factories = [Note::class => static fn (): Note => Note::factory()->create()];
    $entryPoints = ObjectLevelAccessHelpers::entryPoints();
    $unanswered = [];
    $statuses = [];

    Users::signedIn(...NotePermission::cases());

    foreach ($entryPoints as $route) {
        $records = ObjectLevelAccessHelpers::foreignRecords($route, $factories);
        $name = ApplicationRoutes::identifier($route);

        if (is_string($records)) {
            $unanswered[] = $name . ' ' . $records;

            continue;
        }

        $statuses[$name] = $this->json(ObjectLevelAccessHelpers::method($route), route($name, $records))->getStatusCode();
    }

    expect($entryPoints)->not->toBe([])
        ->and($unanswered)->toBe([])
        ->and(array_filter($statuses, static fn (int $status): bool => $status !== 403))->toBe([]);
});

it('finds the routes that take a record id, and fails one whose model has no factory', function (): void {
    $show = ApplicationRoutes::forController(['GET'], 'notes/{note}', ShowController::class);
    $index = ApplicationRoutes::forController(['GET'], 'notes', IndexController::class);

    expect(ObjectLevelAccessHelpers::recordParameters($show))->toBe(['note' => Note::class])
        ->and(ObjectLevelAccessHelpers::recordParameters($index))->toBe([])
        ->and(ObjectLevelAccessHelpers::foreignRecords($show, []))->toBe('has no factory for App\Models\Note in the map of foreign records')
        ->and(ObjectLevelAccessHelpers::method($show))->toBe('GET')
        ->and(ObjectLevelAccessHelpers::method(ApplicationRoutes::forController(['PUT'], 'notes/{note}', ShowController::class)))->toBe('PUT');
});
