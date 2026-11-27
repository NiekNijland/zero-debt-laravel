<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('hashes the password and hides it', function (): void {
    $created = User::factory()->create(['password' => 'a-long-password']);
    $user = User::query()->findOrFail($created->id);

    expect(Hash::check('a-long-password', $user->password))->toBeTrue()
        ->and($user->toArray())->not->toHaveKey('password');
});

it('issues an api token', function (): void {
    $user = User::factory()->create();

    expect($user->createToken('test')->plainTextToken)->toBeString();
});
