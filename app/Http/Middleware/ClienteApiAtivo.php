<?php

namespace App\Http\Middleware;

use App\Models\ClienteApi;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClienteApiAtivo
{
    public const SO_PARCEIRO = 'Esta rota é só para sistemas parceiros.';

    public function handle(Request $request, Closure $next): Response
    {
        $cliente = $request->user();

        if (! $cliente instanceof ClienteApi) {
            abort(403, self::SO_PARCEIRO);
        }

        if (! $cliente->ativo) {
            $cliente->tokens()->delete();

            throw new AuthenticationException;
        }

        return $next($request);
    }
}
