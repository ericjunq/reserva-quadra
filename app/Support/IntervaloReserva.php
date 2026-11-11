<?php

namespace App\Support;

use App\Constants\HorarioReservaConstant;

class IntervaloReserva
{
    public static function bloco(): int
    {
        return (int) config('reservas.bloco_minutos');
    }

    public static function normalizar(mixed $hora): ?string
    {
        if (! is_string($hora) || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:00)?$/', $hora, $partes)) {
            return null;
        }

        return "{$partes[1]}:{$partes[2]}:00";
    }

    public static function minutos(string $hora): int
    {
        [$h, $m] = explode(':', $hora);

        return (int) $h * 60 + (int) $m;
    }

    public static function hora(int $minutos): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutos, 60), $minutos % 60);
    }

    public static function curta(string $hora): string
    {
        return substr($hora, 0, 5);
    }

    public static function arredondar(mixed $hora): mixed
    {
        if (! is_string($hora) || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $hora)) {
            return $hora;
        }

        $bloco = self::bloco();
        $minutos = min((int) round(self::minutos($hora) / $bloco) * $bloco, 24 * 60 - $bloco);

        return self::curta(self::hora($minutos));
    }

    public static function alinhada(string $hora): bool
    {
        return self::minutos($hora) % self::bloco() === 0;
    }

    public static function doPedido(array $dados): ?array
    {
        if ($horario = HorarioReservaConstant::tryFrom((string) ($dados['horario'] ?? ''))) {
            return [$horario->horaInicio(), $horario->horaFim()];
        }

        $inicio = self::normalizar($dados['hora_inicio'] ?? null);
        $fim = self::normalizar($dados['hora_fim'] ?? null);

        return $inicio && $fim && $inicio < $fim ? [$inicio, $fim] : null;
    }
}
