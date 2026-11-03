<?php

namespace App\Models;

use App\Constants\HorarioReservaConstant;
use App\Constants\StatusConstant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reserva extends Model
{
    protected $fillable = [
        'data',
        'horario',
    ];

    protected $attributes = [
        'status' => 'RESERVADO',
    ];

    protected function casts(): array
    {
        return [
            'data'    => 'date',
            'horario' => HorarioReservaConstant::class,
            'status'  => StatusConstant::class,
        ];
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }
}
