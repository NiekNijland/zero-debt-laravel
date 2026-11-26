<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Note;

use App\Http\Requests\ApiRequest;
use Illuminate\Support\Facades\Config;

final class IndexRequest extends ApiRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:' . Config::integer('notes.max_per_page')],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', Config::integer('notes.per_page'));
    }
}
