<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

uses()->group('arch');

arch('models extend the model class and are used by the layers that read them')
    ->expect('App\Models')
    ->toExtend(Model::class)
    ->toOnlyBeUsedIn([
        'App\Policies',
        'App\Support',
        'App\Http',
        'App\Models',
        'App\Modules',
        'App\Providers',
        'Database\Factories',
        'Database\Seeders',
    ]);

arch('factories extend the factory class and define a definition')
    ->expect('Database\Factories')
    ->toExtend(Factory::class)
    ->toHaveMethod('definition')
    ->toOnlyBeUsedIn(['App\Models', 'Database\Seeders']);

arch('the user model is a model')
    ->expect(User::class)
    ->toExtend(Model::class);

arch('factories carry the Factory suffix')
    ->expect('Database\Factories')
    ->classes()
    ->toHaveSuffix('Factory');
