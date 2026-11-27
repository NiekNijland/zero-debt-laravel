<?php

declare(strict_types=1);

use Tests\Support\ModuleNamespaces;

uses()->group('arch');

arch('actions are named after what they do, without an Action suffix')
    ->expect(ModuleNamespaces::of('Actions'))
    ->classes()
    ->not->toHaveSuffix('Action');
