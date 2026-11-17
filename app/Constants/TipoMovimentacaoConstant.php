<?php

namespace App\Constants;

enum TipoMovimentacaoConstant: string
{
    case ENTRADA = 'ENTRADA';
    case BAIXA = 'BAIXA';
    case NAO_DEVOLVIDO = 'NAO_DEVOLVIDO';
    case MANUTENCAO = 'MANUTENCAO';
    case RETORNO = 'RETORNO';
    case AJUSTE = 'AJUSTE';

    public function label(): string
    {
        return match ($this) {
            self::ENTRADA       => 'Entrada',
            self::BAIXA         => 'Baixa',
            self::NAO_DEVOLVIDO => 'Não houve devolução',
            self::MANUTENCAO    => 'Foi para manutenção',
            self::RETORNO       => 'Voltou da manutenção',
            self::AJUSTE        => 'Ajuste de inventário',
        };
    }

    public function exigeReserva(): bool
    {
        return $this === self::NAO_DEVOLVIDO;
    }
}
