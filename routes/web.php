<?php

use App\Http\Controllers\Auth\IngresoController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/ingreso', [IngresoController::class, 'create'])->name('login');
    Route::post('/ingreso', [IngresoController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    // Provisorio: la pantalla de Inicio llega con su propia tarea.
    Route::get('/', fn () => view('inicio'))->name('inicio');
    Route::post('/salir', [IngresoController::class, 'destroy'])->name('logout');
});
