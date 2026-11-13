<?php

namespace App\Models;

use App\Models\Concerns\AtualizaDisponibilidade;
use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Equipamento extends Model
{
    use AtualizaDisponibilidade, Auditavel, HasFactory;

    public const RELACOES_ADMIN = [...Auditoria::RELACOES, 'espacosCompartilhados:id,nome'];

    protected $fillable = ['espaco_id', 'nome', 'quantidade_total', 'ativo'];

    public function casts(): array
    {
        return [
            'quantidade_total' => 'integer',
            'ativo'            => 'boolean',
        ];
    }

    public function reservas(): BelongsToMany
    {
        return $this->belongsToMany(Reserva::class, 'item_reservas')
            ->using(ItemReserva::class)
            ->withPivot(ItemReserva::COLUNAS)
            ->withTimestamps();
    }

    public function espaco(): BelongsTo
    {
        return $this->belongsTo(Espaco::class);
    }

    public function espacosCompartilhados(): BelongsToMany
    {
        return $this->belongsToMany(Espaco::class, 'equipamento_compartilhamentos')->withTimestamps();
    }

    public function scopeUsavelNoEspaco(Builder $query, int $espacoId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('espaco_id', $espacoId)
            ->orWhereIn('id', fn ($sub) => $sub
                ->select('equipamento_id')
                ->from('equipamento_compartilhamentos')
                ->where('espaco_id', $espacoId)));
    }
}
