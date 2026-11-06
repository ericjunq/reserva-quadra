<?php

namespace App\Models;

use App\Constants\SituacaoDevolucaoConstant;
use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ItemReserva extends Pivot
{
    use Auditavel;

    public const COLUNAS = [
        'id',
        'quantidade',
        'situacao_devolucao',
        'quantidade_devolvida',
        'observacao_devolucao',
        'devolvido_em',
        'devolvido_por',
    ];

    protected $table = 'item_reservas';

    public $incrementing = true;

    protected $attributes = [
        'situacao_devolucao' => 'PENDENTE',
    ];

    protected function casts(): array
    {
        return [
            'situacao_devolucao' => SituacaoDevolucaoConstant::class,
            'devolvido_em'       => 'datetime',
        ];
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    public function equipamento(): BelongsTo
    {
        return $this->belongsTo(Equipamento::class);
    }

    public function responsavelDevolucao(): BelongsTo
    {
        return $this->belongsTo(User::class, 'devolvido_por');
    }
}
