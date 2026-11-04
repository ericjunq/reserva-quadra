<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Equipamento extends Model
{
    protected $fillable = ['espaco_id','nome', 'quantidadeTotal', 'ativo'];

    public function casts(): array{
        return [
            'quantidadeTotal' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function reservas(): BelongsToMany
    {
        return $this->belongsToMany(Reserva::class, 'item_reserva')
        ->withPivot('quantidade')
        ->withTimestamps();
    }

    public function espacos(): BelongsTo
    {
        return $this->belongsTo(Espaco::class);
    }
}
