<?php

use App\Http\Controllers\PaginaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PaginaController::class, 'inicio'])->name('inicio');
Route::get('/salas/{codigo}', [PaginaController::class, 'sala'])->name('sala');
