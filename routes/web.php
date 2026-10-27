<?php

use App\Http\Controllers\Auth\IngresoController;
use App\Http\Controllers\ProductoFotoController;
use App\Livewire\Clientes\Formulario as ClienteFormulario;
use App\Livewire\Clientes\Listado as ClienteListado;
use App\Livewire\Productos\Catalogo;
use App\Livewire\Productos\Formulario;
use App\Livewire\Stock\Ajuste as StockAjuste;
use App\Livewire\Stock\Detalle as StockDetalle;
use App\Livewire\Stock\Ingreso as StockIngreso;
use App\Livewire\Stock\Listado as StockListado;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [IngresoController::class, 'create'])->name('login');
    Route::post('/login', [IngresoController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    // Provisorio: la pantalla de Inicio llega con su propia tarea.
    Route::get('/', fn () => view('inicio'))->name('inicio');
    Route::post('/salir', [IngresoController::class, 'destroy'])->name('logout');

    Route::livewire('/productos', Catalogo::class)->name('productos.catalogo');
    Route::livewire('/productos/nuevo', Formulario::class)->name('productos.nuevo');
    Route::livewire('/productos/{producto}/editar', Formulario::class)->name('productos.editar');
    Route::get('/productos/{producto}/foto', ProductoFotoController::class)->name('productos.foto');

    Route::livewire('/clientes', ClienteListado::class)->name('clientes.listado');
    Route::livewire('/clientes/nuevo', ClienteFormulario::class)->name('clientes.nuevo');
    Route::livewire('/clientes/{cliente}/editar', ClienteFormulario::class)->name('clientes.editar');

    Route::livewire('/stock', StockListado::class)->name('stock.listado');
    Route::livewire('/stock/{producto}', StockDetalle::class)->name('stock.detalle');
    Route::livewire('/stock/{producto}/ingreso', StockIngreso::class)->name('stock.ingreso');
    Route::livewire('/stock/{producto}/ajuste', StockAjuste::class)->name('stock.ajuste');
});
