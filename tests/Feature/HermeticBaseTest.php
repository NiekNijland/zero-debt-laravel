<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

it('refuses a stray http request', function (): void {
    expect(Http::preventingStrayRequests())->toBeTrue()
        ->and(fn () => Http::get('https://example.com'))->toThrow(RuntimeException::class);
});

it('refuses a stray process', function (): void {
    expect(Process::preventingStrayProcesses())->toBeTrue();
});

it('freezes the clock', function (): void {
    $before = now();
    Sleep::for(1)->second();

    expect(now()->equalTo($before))->toBeTrue();
});

it('fakes sleeping and records it', function (): void {
    Sleep::for(1)->second();

    Sleep::assertSleptTimes(1);
});

it('generates different ids', function (): void {
    expect(Str::uuid()->toString())->not->toBe(Str::uuid()->toString())
        ->and(Str::random())->not->toBe(Str::random());
});
