<?php

namespace App\Http\Controllers;

use App\Http\Requests\DefinirSenhaRequest;
use App\Http\Requests\EsqueciSenhaRequest;
use App\Http\Requests\TrocarSenhaRequest;
use App\Services\SenhaService;

class SenhaController extends Controller
{
    public function __construct(private SenhaService $senhaService) {}

    public function esqueci(EsqueciSenhaRequest $request)
    {
        $this->senhaService->esqueci($request->validated('email'));

        return response()->json([
            'message' => 'Se o email estiver cadastrado, você receberá um link para redefinir a senha.',
        ]);
    }

    public function redefinir(DefinirSenhaRequest $request)
    {
        $this->senhaService->redefinir($request->all(['token', 'email', 'password', 'password_confirmation']));

        return response()->json(['message' => 'Senha redefinida. Faça login com a nova senha.']);
    }

    public function aceitarConvite(DefinirSenhaRequest $request)
    {
        $this->senhaService->aceitarConvite($request->all(['token', 'email', 'password', 'password_confirmation']));

        return response()->json(['message' => 'Senha definida. Seu acesso está liberado.']);
    }

    public function trocar(TrocarSenhaRequest $request)
    {
        $this->senhaService->trocar($request->user(), $request->validated('password'));

        return response()->json(['message' => 'Senha alterada. As sessões em outros dispositivos foram encerradas.']);
    }
}
