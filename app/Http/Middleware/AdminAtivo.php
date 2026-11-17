<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAtivo
{
    public const SO_ADMIN = 'Esta rota é só para os admins do sistema.';

    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if ($admin && ! $admin instanceof User) {
            abort(403, self::SO_ADMIN);
        }

        if ($admin && ! $admin->ativo) {
            $admin->tokens()->delete();

            throw new AuthenticationException;
        }

        return $next($request);
    }
}
