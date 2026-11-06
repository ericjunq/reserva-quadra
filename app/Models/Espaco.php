<?php

namespace App\Models;

use App\Constants\TipoEspacoConstant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Espaco extends Model
{
    use HasFactory;

    protected $fillable = ['nome', 'tipo', 'ativo', 'limite_semanal_por_curso'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoEspacoConstant::class,
            'ativo' => 'boolean',
            'limite_semanal_por_curso' => 'integer',
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
