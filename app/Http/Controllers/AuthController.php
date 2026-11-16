<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarCodigoLoginRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ReenviarCodigoLoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function login(LoginRequest $request)
    {
        return response()->json($this->authService->login($request->validated()));
    }

    public function confirmarCodigo(ConfirmarCodigoLoginRequest $request)
    {
        return response()->json($this->authService->confirmarCodigo($request->validated()));
    }

    public function reenviarCodigo(ReenviarCodigoLoginRequest $request)
    {
        return response()->json($this->authService->reenviarCodigo($request->validated()));
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return response()->noContent();
    }

    public function logoutGeral(Request $request)
    {
        $this->authService->logoutGeral($request->user());

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return new UserResource($request->user());
    }
}
