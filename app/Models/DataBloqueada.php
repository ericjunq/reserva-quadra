<?php

namespace App\Models;

use App\Constants\OrigemBloqueioConstant;
use App\Models\Concerns\AtualizaDisponibilidade;
use App\Models\Concerns\Auditavel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataBloqueada extends Model
{
    use AtualizaDisponibilidade, Auditavel, HasFactory;

    protected $table = 'datas_bloqueadas';

    protected $fillable = [
        'data_inicio',
        'data_fim',
        'hora_inicio',
        'hora_fim',
        'espaco_id',
        'motivo',
        'origem',
        'criado_por',
    ];

    protected $attributes = [
        'origem' => 'MANUAL',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_fim'    => 'date',
            'origem'      => OrigemBloqueioConstant::class,
        ];
    }

    protected function dataInicio(): Attribute
    {
        return Attribute::set(fn ($valor) => Carbon::parse($valor)->toDateString());
    }

    protected function dataFim(): Attribute
    {
        return Attribute::set(fn ($valor) => Carbon::parse($valor)->toDateString());
    }

    protected function horaInicio(): Attribute
    {
        return Attribute::set(fn ($valor) => $valor === null ? null : Carbon::parse($valor)->format('H:i:s'));
    }

    protected function horaFim(): Attribute
    {
        return Attribute::set(fn ($valor) => $valor === null ? null : Carbon::parse($valor)->format('H:i:s'));
    }

    public function scopeAfetando(Builder $query, string $data, int $espacoId, string $inicio, string $fim): Builder
    {
        return $query
            ->where('data_inicio', '<=', $data)
            ->where('data_fim', '>=', $data)
            ->where(fn ($q) => $q->whereNull('espaco_id')->orWhere('espaco_id', $espacoId))
            ->where(fn ($q) => $q->whereNull('hora_inicio')->orWhere(
                fn ($q) => $q->where('hora_inicio', '<', $fim)->where('hora_fim', '>', $inicio)
            ));
    }

    public function diaInteiro(): bool
    {
        return $this->hora_inicio === null;
    }

    public function espaco(): BelongsTo
    {
        return $this->belongsTo(Espaco::class);
    }
}
