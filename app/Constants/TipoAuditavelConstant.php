<?php

namespace App\Constants;

use App\Models\Aluno;
use App\Models\Curso;
use App\Models\DataBloqueada;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\ItemReserva;
use App\Models\Reserva;
use App\Models\User;

enum TipoAuditavelConstant: string
{
    case ADMIN = 'ADMIN';
    case ALUNO = 'ALUNO';
    case CURSO = 'CURSO';
    case DATA_BLOQUEADA = 'DATA_BLOQUEADA';
    case EQUIPAMENTO = 'EQUIPAMENTO';
    case ESPACO = 'ESPACO';
    case ITEM_RESERVA = 'ITEM_RESERVA';
    case RESERVA = 'RESERVA';

    public function apelido(): string
    {
        return strtolower($this->value);
    }

    public function model(): string
    {
        return match ($this) {
            self::ADMIN          => User::class,
            self::ALUNO          => Aluno::class,
            self::CURSO          => Curso::class,
            self::DATA_BLOQUEADA => DataBloqueada::class,
            self::EQUIPAMENTO    => Equipamento::class,
            self::ESPACO         => Espaco::class,
            self::ITEM_RESERVA   => ItemReserva::class,
            self::RESERVA        => Reserva::class,
        };
    }

    public static function mapaMorph(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $tipo) => [$tipo->apelido() => $tipo->model()])
            ->all();
    }

    public static function doApelido(string $apelido): ?self
    {
        return self::tryFrom(strtoupper($apelido));
    }
}
