<?php

namespace App\Models;

use App\Constants\HorarioReservaConstant;
use App\Constants\StatusConstant;
use App\Constants\TipoReservaConstant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reserva extends Model
{
    protected $fillable = [
        'data_reserva',
        'horario',
        'espaco_id',
        'aluno_id',
        'user_id',
        'status',
        'tipo',
        'descricao',
        'justificativa_revogacao',
        'revogada_em',
        'revogada_por',
    ];

    protected $attributes = [
        'status' => 'RESERVADO',
        'tipo' => 'ALUNO',
    ];

    protected function casts(): array
    {
        return [
            'data_reserva' => 'date',
            'horario'     => HorarioReservaConstant::class,
            'status'      => StatusConstant::class,
            'tipo'        => TipoReservaConstant::class,
            'revogada_em' => 'datetime',
        ];
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    public function espaco(): BelongsTo
    {
        return $this->belongsTo(Espaco::class);
    }

    public function equipamentos(): BelongsToMany
    {
        return $this->belongsToMany(Equipamento::class, 'item_reservas')
            ->withPivot('quantidade')
            ->withTimestamps();
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function revogador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revogada_por');
    }
}
