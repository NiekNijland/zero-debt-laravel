<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use LogicException;
use Pest\Expectation;
use Pest\PendingCalls\TestCall;
use Pest\Preset;

/**
 * @mixin TestCall
 */
final readonly class PestArchCall
{
    /**
     * @return Expectation<mixed>
     */
    public function expect(mixed $value): Expectation
    {
        return new Expectation($value);
    }

    public function preset(): Preset
    {
        throw new LogicException('Only PHPStan reads this type.');
    }
}
