<?php

declare(strict_types=1);

use App\Http\Requests\ApiRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;

it('returns the signed in user as actor', function (): void {
    $user = User::factory()->create();
    $request = new class() extends ApiRequest
    {
    };
    $request->setUserResolver(static fn (): User => $user);

    expect($request->actor()->id)->toBe($user->id);
});

it('refuses a request without a user', function (): void {
    $request = new class() extends ApiRequest
    {
    };

    expect(fn (): User => $request->actor())->toThrow(AuthenticationException::class);
});
