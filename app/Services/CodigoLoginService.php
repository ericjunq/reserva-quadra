<?php

namespace App\Services;

use App\Exceptions\RegraNegocioException;
use App\Mail\CodigoLoginMail;
use App\Models\DesafioLogin;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class CodigoLoginService
{
    public const VALIDADE_MINUTOS = 10;

    public const MAXIMO_REENVIOS = 3;

    public const SEGUNDOS_ENTRE_REENVIOS = 60;

    public const CODIGO_INVALIDO = 'Código inválido ou vencido. Entre de novo se precisar.';

    public function iniciar(User $user): array
    {
        DesafioLogin::where('user_id', $user->id)->whereNull('usado_em')->where('expira_em', '>', now())->update(['expira_em' => now()]);

        $desafio = Str::random(64);
        $codigo = $this->gerarCodigo();

        $registro = DesafioLogin::create([
            'user_id'         => $user->id,
            'desafio_hash'    => $this->hash($desafio),
            'codigo_hash'     => $this->hash($codigo),
            'expira_em'       => now()->addMinutes(self::VALIDADE_MINUTOS),
            'ultimo_envio_em' => now(),
        ]);

        $this->enviar($user, $codigo, fn () => $registro->delete());

        return [
            'etapa'     => 'CODIGO',
            'desafio'   => $desafio,
            'expira_em' => $registro->expira_em->toIso8601String(),
        ];
    }

    public function confirmar(string $desafio, string $codigo): User
    {
        $user = DB::transaction(function () use ($desafio, $codigo) {
            $registro = $this->buscar($desafio);

            if (! $registro?->valido()) {
                return null;
            }

            if (! hash_equals($registro->codigo_hash, $this->hash($codigo))) {
                $registro->increment('tentativas');

                return null;
            }

            $registro->update(['usado_em' => now()]);

            return $registro->user;
        });

        if (! $user?->ativo) {
            throw new AuthenticationException(self::CODIGO_INVALIDO);
        }

        return $user;
    }

    public function reenviar(string $desafio): array
    {
        [$registro, $codigo] = DB::transaction(function () use ($desafio) {
            $registro = $this->buscar($desafio);

            if (! $registro?->valido() || ! $registro->user->ativo) {
                throw new AuthenticationException(self::CODIGO_INVALIDO);
            }

            if ($registro->reenvios >= self::MAXIMO_REENVIOS) {
                throw new RegraNegocioException('Você já pediu o código novo muitas vezes. Entre de novo com a sua senha.', 429);
            }

            $espera = self::SEGUNDOS_ENTRE_REENVIOS - (int) $registro->ultimo_envio_em->diffInSeconds(now());

            if ($espera > 0) {
                throw new RegraNegocioException("Aguarde {$espera} segundos para pedir outro código.", 429, ['segundos' => $espera]);
            }

            $codigo = $this->gerarCodigo();

            $registro->update([
                'codigo_hash'     => $this->hash($codigo),
                'expira_em'       => now()->addMinutes(self::VALIDADE_MINUTOS),
                'tentativas'      => 0,
                'reenvios'        => $registro->reenvios + 1,
                'ultimo_envio_em' => now(),
            ]);

            return [$registro, $codigo];
        });

        $this->enviar($registro->user, $codigo, fn () => $registro->update(['expira_em' => now()]));

        return ['expira_em' => $registro->expira_em->toIso8601String()];
    }

    private function buscar(string $desafio): ?DesafioLogin
    {
        return DesafioLogin::with('user')
            ->where('desafio_hash', $this->hash($desafio))
            ->lockForUpdate()
            ->first();
    }

    private function enviar(User $user, string $codigo, callable $aoFalhar): void
    {
        try {
            Mail::to($user->email)->send(new CodigoLoginMail($user->name, $codigo, self::VALIDADE_MINUTOS));
        } catch (Throwable $erro) {
            $aoFalhar();
            report($erro);

            throw new RegraNegocioException('Não foi possível enviar o código agora. Tente de novo em instantes.', 503);
        }
    }

    private function gerarCodigo(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hash(string $valor): string
    {
        return hash_hmac('sha256', $valor, (string) config('app.key'));
    }
}
