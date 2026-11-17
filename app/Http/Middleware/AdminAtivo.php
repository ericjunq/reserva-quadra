<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAtivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if ($admin && ! $admin->ativo) {
            $admin->tokens()->delete();

            throw new AuthenticationException;
        }

        return $next($request);
    }
}
