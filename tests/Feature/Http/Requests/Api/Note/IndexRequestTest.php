<?php

declare(strict_types=1);

use App\Http\Requests\Api\Note\IndexRequest;

it('reads the page size from the query', function (): void {
    $request = IndexRequest::create('/', parameters: ['per_page' => '7']);

    expect($request->perPage())->toBe(7);
});

it('falls back to the configured page size', function (): void {
    config(['notes.per_page' => 9]);

    expect(IndexRequest::create('/')->perPage())->toBe(9);
});

it('limits the page size to the configured maximum', function (): void {
    config(['notes.max_per_page' => 10]);

    expect(new IndexRequest()->rules()['per_page'])->toContain('max:10');
});
