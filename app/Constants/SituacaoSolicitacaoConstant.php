<?php

namespace App\Constants;

enum SituacaoSolicitacaoConstant: string
{
    case AGUARDANDO = 'AGUARDANDO';
    case APROVADA = 'APROVADA';
    case RECUSADA = 'RECUSADA';
    case VENCIDA = 'VENCIDA';

    public function label(): string
    {
        return match ($this) {
            self::AGUARDANDO => 'pedido aguardando aprovação',
            self::APROVADA   => 'pedido aprovado',
            self::RECUSADA   => 'pedido recusado',
            self::VENCIDA    => 'pedido sem resposta',
        };
    }

    public static function semEfeito(): array
    {
        return [self::RECUSADA->value, self::VENCIDA->value];
    }
}
