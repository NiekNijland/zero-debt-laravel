<?php

declare(strict_types=1);

use App\Models\Note;
use App\Modules\Notes\Enums\NotePermission;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Tests\Support\Providers\Limiters;
use Tests\Support\Users;

it('registers a read and a write limiter with the numbers of the config', function (): void {
    config(['rate-limits.read' => 11, 'rate-limits.write' => 3]);
    $request = Request::create('/');

    $read = Limiters::limit('read', $request);
    $write = Limiters::limit('write', $request);

    expect($read)->toBeInstanceOf(Limit::class)
        ->and($read->maxAttempts)->toBe(11)
        ->and($write->maxAttempts)->toBe(3);
});

it('keys the limit by user and falls back to the ip', function (): void {
    $guest = Limiters::limit('read', Request::create('/', server: ['REMOTE_ADDR' => '10.0.0.1']));
    $user = Users::create();
    $request = Request::create('/');
    $request->setUserResolver(static fn (): Authenticatable => $user);

    $signedIn = Limiters::limit('read', $request);

    expect($guest->key)->toBe('10.0.0.1')
        ->and($signedIn->key)->toBe($user->id);
});

it('answers too many requests above the limit', function (): void {
    config(['rate-limits.write' => 1]);
    $user = Users::signedIn(NotePermission::DELETE);
    $first = Note::factory()->for($user)->create();
    $second = Note::factory()->for($user)->create();

    $this->deleteJson(route('notes.destroy', $first))->assertNoContent();
    $this->deleteJson(route('notes.destroy', $second))->assertTooManyRequests();
});
