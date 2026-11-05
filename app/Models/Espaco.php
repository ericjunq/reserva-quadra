<?php

namespace App\Models;

use App\Constants\TipoEspacoConstant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Espaco extends Model
{

    protected $fillable = ['nome', 'tipo', 'ativo'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoEspacoConstant::class,
            'ativo' => 'boolean'
        ];
    }

    public function equipamentos(): HasMany
    {
        return $this->hasMany(Equipamento::class);
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }
}
