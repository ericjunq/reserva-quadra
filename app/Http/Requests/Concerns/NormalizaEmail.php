<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;

trait NormalizaEmail
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => User::normalizarEmail($this->input('email'))]);
        }
    }
}
