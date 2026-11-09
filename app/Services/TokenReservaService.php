<?php

namespace App\Services;

use App\Constants\FinalidadeTokenConstant;
use App\Exceptions\RegraNegocioException;
use App\Models\Reserva;
use App\Models\TokenReserva;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class TokenReservaService
{
    public const LINK_INVALIDO = 'Link inválido ou expirado.';

    public function gerar(Reserva $reserva, FinalidadeTokenConstant $finalidade, CarbonInterface $expiraEm): string
    {
        $token = Str::random(64);

        $reserva->tokens()->create([
            'finalidade' => $finalidade,
            'token_hash' => $this->hash($token),
            'expira_em'  => $expiraEm,
        ]);

        return $token;
    }

    public function buscarValido(string $token, FinalidadeTokenConstant $finalidade, bool $travar = false): TokenReserva
    {
        $registro = TokenReserva::query()
            ->where('token_hash', $this->hash($token))
            ->where('finalidade', $finalidade)
            ->when($travar, fn ($q) => $q->lockForUpdate())
            ->first();

        if (! $registro || ! $registro->valido()) {
            throw new RegraNegocioException(self::LINK_INVALIDO, 404);
        }

        return $registro;
    }

    public function marcarComoUsado(TokenReserva $registro): void
    {
        $registro->update(['usado_em' => now()]);
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
