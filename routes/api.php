<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AlunoController;
use App\Http\Controllers\ReservaController;

Route::post('/aluno', [AlunoController::class, 'store']);
Route::get('/aluno', [AlunoController::class, 'index']);
Route::post('/reserva', [ReservaController::class, 'store']);