<?php

use App\Http\Controllers\Api\SalaController;
use Illuminate\Support\Facades\Route;

Route::post('/salas', [SalaController::class, 'crear']);
Route::post('/salas/{sala}/unirse', [SalaController::class, 'unirse']);
Route::get('/salas/{sala}/estado', [SalaController::class, 'estado']);
Route::post('/salas/{sala}/accion', [SalaController::class, 'accion']);
Route::post('/salas/{sala}/siguiente-ronda', [SalaController::class, 'siguienteRonda']);
