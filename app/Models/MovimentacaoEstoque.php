<?php

namespace App\Models;

use App\Constants\TipoMovimentacaoConstant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimentacaoEstoque extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'movimentacoes_estoque';

    protected $fillable = [
        'equipamento_id',
        'tipo',
        'quantidade',
        'estado',
        'reserva_id',
        'user_id',
        'quantidade_total_depois',
        'em_manutencao_depois',
    ];

    protected function casts(): array
    {
        return [
            'tipo'                    => TipoMovimentacaoConstant::class,
            'quantidade'              => 'integer',
            'quantidade_total_depois' => 'integer',
            'em_manutencao_depois'    => 'integer',
        ];
    }

    public function equipamento(): BelongsTo
    {
        return $this->belongsTo(Equipamento::class);
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
