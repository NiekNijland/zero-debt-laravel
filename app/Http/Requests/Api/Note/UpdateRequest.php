<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Note;

use App\Http\Requests\ApiRequest;
use App\Modules\Notes\Data\NoteInputData;
use App\Modules\Notes\Enums\NoteStatus;
use Illuminate\Validation\Rule;

final class UpdateRequest extends ApiRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'status' => ['required', Rule::enum(NoteStatus::class)],
            'pinned' => ['required', 'boolean'],
        ];
    }

    public function toData(): NoteInputData
    {
        return NoteInputData::from($this->validated());
    }
}
