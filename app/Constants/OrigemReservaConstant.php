<?php

namespace App\Constants;

enum OrigemReservaConstant: string
{
    case SITE = 'SITE';
    case ADMIN = 'ADMIN';
    case REGISTRO_POSTERIOR = 'REGISTRO_POSTERIOR';
    case API = 'API';

    public function label(): string
    {
        return match ($this) {
            self::SITE               => 'Site',
            self::ADMIN              => 'Evento do admin',
            self::REGISTRO_POSTERIOR => 'Registro posterior',
            self::API                => 'Sistema parceiro',
        };
    }
}
