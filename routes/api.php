<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AlunoController;

Route::post('/aluno', [AlunoController::class, 'store']);
Route::get('/aluno', [AlunoController::class, 'index']);