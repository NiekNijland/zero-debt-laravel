<?php

declare(strict_types=1);

use Tests\Support\ModuleNamespaces;

uses()->group('arch');

arch('controllers are final readonly')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();

arch('actions are final readonly')
    ->expect(ModuleNamespaces::of('Actions'))
    ->classes()
    ->toBeFinal()
    ->toBeReadonly();
