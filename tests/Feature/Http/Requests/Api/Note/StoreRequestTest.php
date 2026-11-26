<?php

declare(strict_types=1);

use App\Http\Requests\Api\Note\StoreRequest;
use App\Modules\Notes\Enums\NoteStatus;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Validator;

it('validates the four fields', function (): void {
    $validator = Validator::make(['title' => 'A', 'body' => 'B', 'status' => 'draft', 'pinned' => true], new StoreRequest()->rules());

    expect($validator->passes())->toBeTrue();
});

it('turns validated input into data', function (): void {
    $request = StoreRequest::create('/', 'POST', ['title' => 'A', 'body' => 'B', 'status' => 'published', 'pinned' => true]);
    $request->setContainer(app())->setRedirector(resolve(Redirector::class))->validateResolved();

    expect($request->toData()->status)->toBe(NoteStatus::PUBLISHED);
});
