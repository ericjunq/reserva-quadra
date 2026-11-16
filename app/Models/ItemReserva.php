<?php

namespace App\Models;

use App\Constants\SituacaoDevolucaoConstant;
use App\Constants\SituacaoSolicitacaoConstant;
use App\Models\Concerns\AtualizaDisponibilidade;
use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ItemReserva extends Pivot
{
    use AtualizaDisponibilidade, Auditavel;

    public const COLUNAS = [
        'id',
        'quantidade',
        'situacao_devolucao',
        'quantidade_devolvida',
        'observacao_devolucao',
        'devolvido_em',
        'devolvido_por',
        'situacao_solicitacao',
        'responder_ate',
        'respondida_em',
        'respondida_por',
        'justificativa_recusa',
    ];

    protected $table = 'item_reservas';

    public $incrementing = true;

    protected $attributes = [
        'situacao_devolucao' => 'PENDENTE',
    ];

    protected function casts(): array
    {
        return [
            'situacao_devolucao'   => SituacaoDevolucaoConstant::class,
            'devolvido_em'         => 'datetime',
            'situacao_solicitacao' => SituacaoSolicitacaoConstant::class,
            'respondida_em'        => 'datetime',
            'responder_ate'        => 'datetime',
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
