<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelData\Data;
use Tests\Support\ModuleNamespaces;

uses()->group('arch');

arch('middleware handles the request')
    ->expect('App\Http\Middleware')
    ->classes()
    ->toHaveMethod('handle');

arch('providers extend ServiceProvider, carry the ServiceProvider suffix and are used by providers only')
    ->expect('App\Providers')
    ->classes()
    ->toExtend(ServiceProvider::class)
    ->toHaveSuffix('ServiceProvider')
    ->toOnlyBeUsedIn('App\Providers');

arch('models extend the model class')
    ->expect('App\Models')
    ->classes()
    ->toExtend(Model::class);

arch('models carry no Model suffix')
    ->expect('App\Models')
    ->classes()
    ->not->toHaveSuffix('Model');

arch('policies carry the Policy suffix')
    ->expect('App\Policies')
    ->classes()
    ->toHaveSuffix('Policy');

arch('enums sit in an Enums folder and are enums')
    ->expect('App\Modules\*\Enums')
    ->toBeEnums();

arch('actions have a handle method')
    ->expect(ModuleNamespaces::of('Actions'))
    ->toHaveMethod('handle');

arch('a class in a Data namespace carries the Data suffix')
    ->expect('App\Modules\*\Data')
    ->classes()
    ->toHaveSuffix('Data');

arch('every Data object carries the Data suffix')
    ->expect('App')
    ->classes()
    ->extending(Data::class)
    ->toHaveSuffix('Data');

arch('queued classes declare no displayName')
    ->expect('App')
    ->classes()
    ->implementing(ShouldQueue::class)
    ->not->toHaveMethod('displayName');
