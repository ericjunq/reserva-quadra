<?php

namespace App\Models;

use App\Models\Concerns\AtualizaDisponibilidade;
use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Equipamento extends Model
{
    use AtualizaDisponibilidade, Auditavel, HasFactory;

    protected $fillable = ['espaco_id', 'nome', 'quantidade_total', 'ativo', 'compartilhavel'];

    public function casts(): array
    {
        return [
            'quantidade_total' => 'integer',
            'ativo'            => 'boolean',
            'compartilhavel'   => 'boolean',
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
}
