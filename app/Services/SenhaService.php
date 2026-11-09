<?php

namespace App\Services;

use App\Exceptions\RegraNegocioException;
use App\Mail\RedefinicaoSenhaMail;
use App\Mail\SenhaAlteradaMail;
use App\Models\User;
use App\Support\FrontEnd;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class SenhaService
{
    public const LINK_INVALIDO = 'Link inválido ou expirado.';

    public function esqueci(string $email): void
    {
        Password::broker('admins')->sendResetLink(
            ['email' => $email, 'ativo' => true],
            function (User $admin, string $token) {
                Mail::to($admin->email)->queue(new RedefinicaoSenhaMail(
                    $admin->name,
                    FrontEnd::url("redefinir-senha/{$token}"),
                    now()->addMinutes(config('auth.passwords.admins.expire')),
                ));
            },
        );
    }

    public function redefinir(array $dados): void
    {
        $this->definirPeloBroker('admins', $dados);
    }

    public function aceitarConvite(array $dados): void
    {
        $this->definirPeloBroker('convites', $dados);
    }

    public function trocar(User $admin, string $novaSenha): void
    {
        $admin->forceFill(['password' => $novaSenha, 'remember_token' => Str::random(60)])->save();

        $atual = $admin->currentAccessToken();
        $admin->tokens()->when($atual?->id, fn ($q, $id) => $q->where('id', '!=', $id))->delete();

        $this->avisarAlteracao($admin);
    }

    private function definirPeloBroker(string $broker, array $dados): void
    {
        $status = Password::broker($broker)->reset(
            [
                'email'                 => $dados['email'],
                'token'                 => $dados['token'],
                'password'              => $dados['password'],
                'password_confirmation' => $dados['password_confirmation'] ?? $dados['password'],
                'ativo'                 => true,
            ],
            function (User $admin, string $senha) {
                $admin->forceFill([
                    'password'          => $senha,
                    'remember_token'    => Str::random(60),
                    'convite_aceito_em' => $admin->convite_aceito_em ?? now(),
                ])->save();

                Password::broker('admins')->deleteToken($admin);
                Password::broker('convites')->deleteToken($admin);
                $admin->tokens()->delete();
                $this->avisarAlteracao($admin);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new RegraNegocioException(self::LINK_INVALIDO);
        }
    }

    private function avisarAlteracao(User $admin): void
    {
        Mail::to($admin->email)->queue(new SenhaAlteradaMail($admin->name, now()));
    }
}
