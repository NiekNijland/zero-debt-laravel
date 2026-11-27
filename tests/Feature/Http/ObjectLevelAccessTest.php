<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Permission;
use Tests\Support\ApplicationRoutes;
use Tests\Support\Feature\Http\ObjectLevelAccessHelpers;
use Tests\Support\Users;

it('refuses every route with a record id for a record that belongs to someone else', function (): void {
    $this->seed(DatabaseSeeder::class);

    $entryPoints = ObjectLevelAccessHelpers::entryPoints();
    $factories = ObjectLevelAccessHelpers::factories($entryPoints);
    $unanswered = [];
    $statuses = [];

    Users::signedIn()->givePermissionTo(Permission::query()->get());

    foreach ($entryPoints as $route) {
        $records = ObjectLevelAccessHelpers::foreignRecords($route, $factories);
        $name = ApplicationRoutes::identifier($route);

        if (is_string($records)) {
            $unanswered[] = $name . ' ' . $records;

            continue;
        }

        $statuses[$name] = $this->json(ObjectLevelAccessHelpers::method($route), route($name, $records))->getStatusCode();
    }

    expect($unanswered)->toBe([])
        ->and(array_filter($statuses, static fn (int $status): bool => $status !== 403))->toBe([]);
});

it('finds the routes that take a record id, and fails one whose model has no factory', function (): void {
    $show = ApplicationRoutes::forAction(['GET'], 'users/{user}', static fn (User $user): string => $user->name);
    $index = ApplicationRoutes::forAction(['GET'], 'users', static fn (): string => '');

    expect(ObjectLevelAccessHelpers::recordParameters($show))->toBe(['user' => User::class])
        ->and(ObjectLevelAccessHelpers::recordParameters($index))->toBe([])
        ->and(ObjectLevelAccessHelpers::factories([$show, $index]))->toHaveKey(User::class)
        ->and(ObjectLevelAccessHelpers::foreignRecords($show, []))->toBe('has no factory for App\Models\User in the map of foreign records')
        ->and(ObjectLevelAccessHelpers::method($show))->toBe('GET')
        ->and(ObjectLevelAccessHelpers::method(ApplicationRoutes::forAction(['PUT'], 'users/{user}', static fn (User $user): string => $user->name)))->toBe('PUT');
});
