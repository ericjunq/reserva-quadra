<?php

use App\Http\Controllers\AlunoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\DataBloqueadaController;
use App\Http\Controllers\EquipamentoController;
use App\Http\Controllers\EspacoController;
use App\Http\Controllers\ExportacaoReservasController;
use App\Http\Controllers\MinhasReservasController;
use App\Http\Controllers\ReservaController;
use Illuminate\Support\Facades\Route;

Route::get('/cursos', [CursoController::class, 'ativos']);
Route::get('/espacos', [EspacoController::class, 'ativos']);
Route::get('/espacos/{espaco}/equipamentos', [EquipamentoController::class, 'ativos']);
Route::get('/espacos/{espaco}/disponibilidade', [EspacoController::class, 'disponibilidade'])->middleware('throttle:60,1');

Route::post('/alunos', [AlunoController::class, 'store'])->middleware('throttle:10,1');
Route::post('/reservas', [ReservaController::class, 'store'])->middleware('throttle:10,1');
Route::post('/minhas-reservas', [MinhasReservasController::class, 'index'])->middleware('throttle:10,1');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-geral', [AuthController::class, 'logoutGeral']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::prefix('admin')->group(function () {
        foreach ([
            'equipamentos' => EquipamentoController::class,
            'espacos'      => EspacoController::class,
            'cursos'       => CursoController::class,
        ] as $nome => $controller) {
            Route::apiResource($nome, $controller)->except('update');
            Route::patch("{$nome}/{".str($nome)->singular().'}', [$controller, 'update'])->name("{$nome}.update");
        }

        Route::get('/alunos', [AlunoController::class, 'index']);
        Route::get('/alunos/{aluno}', [AlunoController::class, 'show']);
        Route::patch('/alunos/{aluno}', [AlunoController::class, 'update']);
        Route::delete('/alunos/{aluno}', [AlunoController::class, 'destroy']);
        Route::post('/alunos/{aluno}/anonimizar', [AlunoController::class, 'anonimizar']);

        Route::get('/datas-bloqueadas', [DataBloqueadaController::class, 'index']);
        Route::post('/datas-bloqueadas', [DataBloqueadaController::class, 'store']);
        Route::get('/datas-bloqueadas/{dataBloqueada}', [DataBloqueadaController::class, 'show']);
        Route::patch('/datas-bloqueadas/{dataBloqueada}', [DataBloqueadaController::class, 'update']);
        Route::delete('/datas-bloqueadas/{dataBloqueada}', [DataBloqueadaController::class, 'destroy']);

        Route::get('/reservas', [ReservaController::class, 'index']);
        Route::get('/reservas/exportar', [ExportacaoReservasController::class, 'exportar']);
        Route::get('/reservas/exportar/colunas', [ExportacaoReservasController::class, 'colunas']);
        Route::get('/reservas/{reserva}', [ReservaController::class, 'show']);
        Route::post('/reservas/eventos', [ReservaController::class, 'storeEvento']);
        Route::post('/reservas/{reserva}/revogar', [ReservaController::class, 'revogar']);
        Route::post('/reservas/{reserva}/devolucao', [ReservaController::class, 'registrarDevolucao']);
    });
});
