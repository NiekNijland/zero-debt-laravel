<?php

declare(strict_types=1);

use App\Http\Requests\Api\Note\IndexRequest;
use App\Http\Requests\ApiRequest;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;

it('returns the signed in user as actor', function (): void {
    $user = User::factory()->create();
    $request = IndexRequest::create('/');
    $request->setUserResolver(static fn (): User => $user);

    expect($request)->toBeInstanceOf(ApiRequest::class)
        ->and($request->actor()->id)->toBe($user->id);
});

it('refuses a request without a user', function (): void {
    $request = IndexRequest::create('/');

    expect(fn (): User => $request->actor())->toThrow(AuthenticationException::class);
});
