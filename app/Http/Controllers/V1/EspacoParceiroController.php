<?php

namespace App\Http\Controllers\V1;

use App\Constants\TipoEspacoConstant;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarDisponibilidadeRequest;
use App\Http\Requests\ConsultarEquipamentosNoIntervaloRequest;
use App\Http\Resources\V1\EquipamentoParceiroResource;
use App\Http\Resources\V1\EspacoParceiroResource;
use App\Models\Espaco;
use App\Services\DisponibilidadeService;
use App\Services\EquipamentoService;
use App\Services\EspacoService;
use Illuminate\Http\Request;

class EspacoParceiroController extends Controller
{
    public function __construct(
        private EspacoService $espacoService,
        private EquipamentoService $equipamentoService,
    ) {}

    public function index(Request $request)
    {
        $tipo = TipoEspacoConstant::tryFrom((string) $request->query('tipo'));

        return EspacoParceiroResource::collection($this->espacoService->listarAtivos($tipo));
    }

    public function disponibilidade(ConsultarDisponibilidadeRequest $request, Espaco $espaco, DisponibilidadeService $disponibilidadeService)
    {
        abort_unless($espaco->ativo, 404);

        return response()->json([
            'data' => $disponibilidadeService->consultar($espaco, $request->input('de'), $request->ate(), $request->curso()),
        ]);
    }

    public function equipamentos(Espaco $espaco)
    {
        abort_unless($espaco->ativo, 404);

        return EquipamentoParceiroResource::collection($this->equipamentoService->listarAtivos($espaco));
    }

    public function equipamentosDisponiveis(ConsultarEquipamentosNoIntervaloRequest $request, Espaco $espaco)
    {
        abort_unless($espaco->ativo, 404);

        [$inicio, $fim] = $request->intervalo();

        return response()->json([
            'data' => $this->equipamentoService->disponibilidadeNoIntervalo($espaco, $request->input('data'), $inicio, $fim),
        ]);
    }
}
