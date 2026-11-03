<?php

namespace App\Constants;
use Carbon\CarbonImmutable;

enum HorarioReservaConstant: string
{
    case PRIMEIRO_HORARIO = "PRIMEIRO_HORARIO";
    case SEGUNDO_HORARIO = "SEGUNDO_HORARIO";

    public const DURACAO_MINUTOS = 90;

    public function inicio (): string
    {
        return match ($this) {
             self::PRIMEIRO_HORARIO => '19:00',
             self::SEGUNDO_HORARIO => '20:30',
        };
    }

    public function fim (): string
    {
        return CarbonImmutable::createFromFormat('H:i', $this->inicio())
            ->addMinutes(self::DURACAO_MINUTOS)
            ->format('H:i');
    }

    public function label(): string
    {
        return "{$this->inicio()} - {$this->fim}";
    }
}
