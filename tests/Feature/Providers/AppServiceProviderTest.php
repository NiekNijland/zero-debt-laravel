<?php

declare(strict_types=1);

use App\Models\Note;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Tests\Support\Providers\ProductionBoot;

afterEach(function (): void {
    ProductionBoot::reset();
});

it('makes the models strict in every environment', function (): void {
    expect(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())->toBeTrue()
        ->and(Model::isAutomaticallyEagerLoadingRelationships())->toBeFalse();
});

it('hands out immutable dates', function (): void {
    expect(now())->toBeInstanceOf(CarbonImmutable::class);
});

it('requires a twelve character password in every environment', function (): void {
    $rule = Password::default();

    expect($rule)->toBeInstanceOf(Password::class)
        ->and(validator(['password' => 'Short1'], ['password' => [Password::default()]])->fails())->toBeTrue()
        ->and(validator(['password' => 'LongEnough123'], ['password' => [Password::default()]])->passes())->toBeTrue();
});

it('forces https urls in production', function (): void {
    ProductionBoot::run();

    expect(URL::to('/x'))->toStartWith('https://');
});

it('keeps http urls outside production', function (): void {
    expect(URL::to('/x'))->toStartWith('http://');
});

it('refuses destructive commands in production', function (): void {
    ProductionBoot::run();

    expect(Artisan::call('db:wipe'))->toBe(1)
        ->and(Artisan::output())->toContain('prohibited');
});

it('allows destructive commands outside production', function (): void {
    expect(Artisan::call('db:wipe'))->toBe(0);
});

it('reports a lazy loading violation in production instead of throwing', function (): void {
    ProductionBoot::run();
    Exceptions::fake();
    Note::factory()->count(2)->create();

    $note = Note::query()->get()->firstOrFail();
    expect($note->user)->toBeInstanceOf(User::class);

    Exceptions::assertReported(LazyLoadingViolationException::class);
});

it('throws on a missing attribute in production too', function (): void {
    ProductionBoot::run();
    Note::factory()->create();
    $note = Note::query()->select('id')->firstOrFail();

    expect(fn (): string => $note->title)->toThrow(MissingAttributeException::class);
});

it('checks the password against known breaches in production only', function (): void {
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
    ProductionBoot::run();

    expect(validator(['password' => 'LongEnough123'], ['password' => [Password::default()]])->passes())->toBeTrue();

    Http::assertSentCount(1);
});

it('does not check breaches outside production', function (): void {
    Http::fake();

    validator(['password' => 'LongEnough123'], ['password' => [Password::default()]])->passes();

    Http::assertNothingSent();
});
