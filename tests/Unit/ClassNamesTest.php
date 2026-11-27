<?php

declare(strict_types=1);

use App\Modules\Localization\Enums\Locale;
use Illuminate\Support\Traits\Macroable;
use Tests\Support\ClassNames;

uses()->group('arch');

it('accepts the name of a class, an interface, an enum and a trait', function (string $name): void {
    expect(ClassNames::of($name))->toBe($name);
})->with([
    'a class' => [ArrayObject::class],
    'an interface' => [Countable::class],
    'an enum' => [Locale::class],
    'a trait' => [Macroable::class],
]);

it('refuses a name that is no type', function (): void {
    expect(static fn (): string => ClassNames::of('Not\\A\\Type'))->toThrow(InvalidArgumentException::class, 'Not\A\Type is not a known class.');
});
