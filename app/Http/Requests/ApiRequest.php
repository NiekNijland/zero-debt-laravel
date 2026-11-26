<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest;

abstract class ApiRequest extends FormRequest
{
    final public function actor(): User
    {
        $user = $this->user();

        return $user instanceof User ? $user : throw new AuthenticationException();
    }
}
