<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aluno extends Model
{
    use Auditavel, HasFactory;

    protected $fillable = ['nome_completo', 'registro_academico', 'email', 'curso_id', 'ativo', 'anonimizado_em'];

    protected $attributes = [
        'ativo' => true,
    ];

    protected function casts(): array
    {
        return [
            'ativo'          => 'boolean',
            'anonimizado_em' => 'datetime',
        ];
    }

    public function anonimizado(): bool
    {
        return $this->anonimizado_em !== null;
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }
}
