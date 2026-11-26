<?php

declare(strict_types=1);

uses()->group('arch');

arch('controllers are final readonly')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('actions are final readonly')
    ->expect('App\Modules\*\Actions')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();
