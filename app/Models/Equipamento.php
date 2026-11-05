<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Equipamento extends Model
{
    protected $fillable = ['espaco_id', 'nome', 'quantidade_total', 'ativo', 'compartilhavel'];

    public function casts(): array{
        return [
            'quantidade_total' => 'integer',
            'ativo' => 'boolean',
            'compartilhavel' => 'boolean',
        ];
    }

    public function reservas(): BelongsToMany
    {
        return $this->belongsToMany(Reserva::class, 'item_reservas')
        ->withPivot('quantidade')
        ->withTimestamps();
    }

    public function espaco(): BelongsTo
    {
        return $this->belongsTo(Espaco::class);
    }
}
