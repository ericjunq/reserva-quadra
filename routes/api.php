<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AlunoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EquipamentoController;
use App\Http\Controllers\ReservaController;

Route::post('/alunos', [AlunoController::class, 'store']);
Route::get('/alunos', [AlunoController::class, 'index']);
Route::post('/reservas', [ReservaController::class, 'store']);
Route::post('/equipamentos', [EquipamentoController::class, 'ativos']);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');


Route::middleware('auth:sanctum')->group(function (){
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-geral', [AuthController::class, 'logoutGeral']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::prefix('admin')->group(function() {
        Route::apiResource('equipamentos', EquipamentoController::class);
    });
});
