<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curso extends Model
{
    protected $fillable = ['nome', 'ativo'];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function alunos(): HasMany
    {
        return $this->hasMany(Aluno::class);
    }
}
