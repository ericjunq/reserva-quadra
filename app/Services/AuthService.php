<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function login(array $data): array
    {
        $user = User::where('email', User::normalizarEmail($data['email']))->first();

        if (! $user || ! $user->ativo || ! Hash::check($data['password'], $user->password)) {
            throw new AuthenticationException('Credenciais inválidas');
        }

        $expira_em = now()->addMinutes(config('sanctum.expiration'));
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
