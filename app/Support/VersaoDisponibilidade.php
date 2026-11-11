<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class VersaoDisponibilidade
{
    private const CHAVE = 'disponibilidade.versao';

    private const MINUTOS_POR_FAIXA = 5;

    public static function avancar(): void
    {
        Cache::forever(self::CHAVE, (string) Str::ulid());
    }

    public static function atual(): string
    {
        $base = Cache::rememberForever(self::CHAVE, fn () => (string) Str::ulid());
        $agora = now();
        $faixa = intdiv($agora->hour * 60 + $agora->minute, self::MINUTOS_POR_FAIXA);

        return substr(sha1("{$base}|{$agora->toDateString()}|{$faixa}"), 0, 16);
    }
}
