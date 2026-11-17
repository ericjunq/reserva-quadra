<?php

namespace App\Constants;

enum PermissaoApiConstant: string
{
    case ESPACOS_LER = 'espacos:ler';
    case RESERVAS_CRIAR = 'reservas:criar';
    case RESERVAS_LER = 'reservas:ler';
    case RESERVAS_CANCELAR = 'reservas:cancelar';

    public function label(): string
    {
        return match ($this) {
            self::ESPACOS_LER       => 'Ver espaços, calendário e equipamentos',
            self::RESERVAS_CRIAR    => 'Reservar para um aluno pelo RA',
            self::RESERVAS_LER      => 'Ver as reservas criadas pelo sistema',
            self::RESERVAS_CANCELAR => 'Cancelar as reservas criadas pelo sistema',
        };
    }

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
