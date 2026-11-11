<?php

namespace App\Models;

use App\Constants\ModoHorarioConstant;
use App\Constants\TipoEspacoConstant;
use App\Models\Concerns\AtualizaDisponibilidade;
use App\Models\Concerns\Auditavel;
use App\Support\IntervaloReserva;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Espaco extends Model
{
    use AtualizaDisponibilidade, Auditavel, HasFactory;

    protected $fillable = [
        'nome',
        'tipo',
        'ativo',
        'limite_semanal_por_curso',
        'modo_horario',
        'abre_as',
        'fecha_as',
        'duracao_maxima_minutos',
    ];

    protected $attributes = [
        'modo_horario' => 'FIXO',
    ];

    protected function casts(): array
    {
        return [
            'tipo'                     => TipoEspacoConstant::class,
            'modo_horario'             => ModoHorarioConstant::class,
            'ativo'                    => 'boolean',
            'limite_semanal_por_curso' => 'integer',
            'duracao_maxima_minutos'   => 'integer',
        ];
    }

    protected function abreAs(): Attribute
    {
        return Attribute::set(fn ($valor) => IntervaloReserva::normalizar($valor));
    }

    protected function fechaAs(): Attribute
    {
        return Attribute::set(fn ($valor) => IntervaloReserva::normalizar($valor));
    }

    public function livre(): bool
    {
        return $this->modo_horario === ModoHorarioConstant::LIVRE;
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
