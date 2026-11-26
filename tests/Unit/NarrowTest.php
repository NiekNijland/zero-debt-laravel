<?php

declare(strict_types=1);

use Tests\Support\Narrow;

uses()->group('arch');

it('narrows a scalar, a null and a stringable to a string and refuses anything else', function (): void {
    $stringable = new class() implements Stringable
    {
        #[Override]
        public function __toString(): string
        {
            return 'text';
        }
    };

    expect(Narrow::string('a'))->toBe('a')
        ->and(Narrow::string(3))->toBe('3')
        ->and(Narrow::string(null))->toBe('')
        ->and(Narrow::string($stringable))->toBe('text')
        ->and(static fn (): string => Narrow::string([]))->toThrow(LogicException::class, 'The value is not a string: array');
});

it('narrows an array to a string-keyed map and refuses anything else', function (): void {
    expect(Narrow::map(['a' => 1, 2 => 'b']))->toBe(['a' => 1, '2' => 'b'])
        ->and(static fn (): array => Narrow::map('x'))->toThrow(LogicException::class, 'The value is not an array: string');
});

it('narrows an array to a list of strings and to a map of strings', function (): void {
    expect(Narrow::strings(['a' => 1, 'b' => 'x']))->toBe(['1', 'x'])
        ->and(Narrow::stringMap(['a' => 1, 'b' => null]))->toBe(['a' => '1', 'b' => '']);
});
