<?php

declare(strict_types=1);

use App\Http\Requests\Api\Note\UpdateRequest;
use App\Modules\Notes\Enums\NoteStatus;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Validator;

it('rejects a status that is not a case', function (): void {
    $validator = Validator::make(['title' => 'A', 'body' => 'B', 'status' => 'archived', 'pinned' => false], new UpdateRequest()->rules());

    expect($validator->fails())->toBeTrue();
});

it('turns validated input into data', function (): void {
    $request = UpdateRequest::create('/', 'PUT', ['title' => 'A', 'body' => 'B', 'status' => 'draft', 'pinned' => false]);
    $request->setContainer(app())->setRedirector(resolve(Redirector::class))->validateResolved();

    expect($request->toData()->status)->toBe(NoteStatus::DRAFT);
});
