<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private CodigoLoginService $codigos) {}

    public function login(array $data): array
    {
        $user = User::where('email', User::normalizarEmail($data['email']))->first();

        if (! $user || ! $user->ativo || ! Hash::check($data['password'], $user->password)) {
            throw new AuthenticationException('Credenciais inválidas');
        }

        return config('reservas.login_com_codigo') ? $this->codigos->iniciar($user) : $this->emitirToken($user);
    }

    public function confirmarCodigo(array $data): array
    {
        return $this->emitirToken($this->codigos->confirmar($data['desafio'], $data['codigo']));
    }

    public function reenviarCodigo(array $data): array
    {
        return $this->codigos->reenviar($data['desafio']);
    }

    private function emitirToken(User $user): array
    {
        $expira_em = now()->addMinutes(config('reservas.sessao_admin_minutos'));
        $token = $user->createToken('api', ['*'], $expira_em);

        return [
            'token'      => $token->plainTextToken,
            'expires_at' => $expira_em->toIso8601String(),
            'user'       => (new UserResource($user))->resolve(),
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function logoutGeral(User $user): void
    {
        $user->tokens()->delete();
    }
}
