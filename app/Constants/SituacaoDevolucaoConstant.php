<?php

namespace App\Constants;

enum SituacaoDevolucaoConstant: string
{
    case PENDENTE = 'PENDENTE';
    case DEVOLVIDO = 'DEVOLVIDO';
    case DEVOLVIDO_COM_AVARIA = 'DEVOLVIDO_COM_AVARIA';
    case NAO_DEVOLVIDO = 'NAO_DEVOLVIDO';

    public function temPendencia(): bool
    {
        return in_array($this, [self::DEVOLVIDO_COM_AVARIA, self::NAO_DEVOLVIDO], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDENTE             => 'Pendente',
            self::DEVOLVIDO            => 'Devolvido',
            self::DEVOLVIDO_COM_AVARIA => 'Devolvido com avaria',
            self::NAO_DEVOLVIDO        => 'Não devolvido',
        };
    }
}
