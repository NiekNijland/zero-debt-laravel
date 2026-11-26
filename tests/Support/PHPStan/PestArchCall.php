<?php

declare(strict_types=1);

namespace Tests\Support\PHPStan;

use LogicException;
use Pest\Preset;

final readonly class PestArchCall
{
    public function preset(): Preset
    {
        throw new LogicException('Only PHPStan reads this type.');
    }
}
