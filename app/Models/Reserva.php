<?php

namespace App\Models;

use App\Constants\HorarioReservaConstant;
use App\Constants\StatusConstant;
use App\Constants\TipoReservaConstant;
use App\Models\Concerns\Auditavel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reserva extends Model
{
    use Auditavel, HasFactory;

    protected $fillable = [
        'data_reserva',
        'horario',
        'hora_inicio',
        'hora_fim',
        'espaco_id',
        'aluno_id',
        'criado_por',
        'status',
        'tipo',
        'descricao',
        'justificativa_revogacao',
        'revogada_em',
        'revogada_por',
    ];

    protected $attributes = [
        'status' => 'RESERVADO',
        'tipo'   => 'ALUNO',
    ];

    protected function casts(): array
    {
        return [
            'data_reserva' => 'date',
            'horario'      => HorarioReservaConstant::class,
            'status'       => StatusConstant::class,
            'tipo'         => TipoReservaConstant::class,
            'revogada_em'  => 'datetime',
        ];
    }

    protected function dataReserva(): Attribute
    {
        return Attribute::set(fn ($valor) => Carbon::parse($valor)->toDateString());
    }

    public function scopeAtivasFuturas(Builder $query): Builder
    {
        $hoje = today()->toDateString();

        return $query
            ->where('status', StatusConstant::RESERVADO)
            ->where(fn ($q) => $q
                ->where('data_reserva', '>', $hoje)
                ->orWhere(fn ($q) => $q
                    ->where('data_reserva', $hoje)
                    ->where('hora_fim', '>', now()->format('H:i:s'))));
    }

    public function scopeJaIniciadas(Builder $query): Builder
    {
        $hoje = today()->toDateString();

        return $query->where(fn ($q) => $q
            ->where('data_reserva', '<', $hoje)
            ->orWhere(fn ($q) => $q
                ->where('data_reserva', $hoje)
                ->where('hora_inicio', '<=', now()->format('H:i:s'))));
    }

    public function iniciada(): bool
    {
        return $this->data_reserva->toDateString() < today()->toDateString()
            || ($this->data_reserva->isToday() && $this->hora_inicio <= now()->format('H:i:s'));
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
            ->using(ItemReserva::class)
            ->withPivot(ItemReserva::COLUNAS)
            ->withTimestamps();
    }

    public function revogador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revogada_por');
    }
}
