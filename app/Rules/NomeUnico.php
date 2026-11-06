<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class NomeUnico implements ValidationRule
{
    public function __construct(
        private string $tabela,
        private ?int $ignorarId = null,
        private array $escopo = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $existe = DB::table($this->tabela)
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower(trim($value))])
            ->when($this->ignorarId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->where($this->escopo)
            ->exists();

        if ($existe) {
            $fail('Já existe um cadastro com esse nome (maiúsculas e minúsculas não diferenciam).');
        }
    }
}
