<?php

declare(strict_types=1);

use App\Http\Requests\ApiRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelData\Data;

uses()->group('arch');

arch('the API form requests extend the API request, carry the Request suffix and define rules()')
    ->expect('App\Http\Requests\Api')
    ->classes()
    ->toExtend(ApiRequest::class)
    ->toHaveSuffix('Request')
    ->toHaveMethod('rules');

arch('the API request is an abstract form request')
    ->expect(ApiRequest::class)
    ->toExtend(FormRequest::class)
    ->toBeAbstract();

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
    ->expect('App\Modules\*\Actions')
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
