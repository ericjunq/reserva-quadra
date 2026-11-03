<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aluno extends Model
{
    protected $fillable = ["nome_completo", "registro_academico", "email", "curso"];

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

}
