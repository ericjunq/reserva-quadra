<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService){}

    public function login(LoginRequest $request)
    {
        return response()->json($this->authService->login($request->validated()));
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
        return response()->json($request->user());
    }

}
