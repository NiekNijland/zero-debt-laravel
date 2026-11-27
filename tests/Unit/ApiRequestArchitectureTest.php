<?php

declare(strict_types=1);

use App\Http\Requests\ApiRequest;
use Illuminate\Foundation\Http\FormRequest;

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
