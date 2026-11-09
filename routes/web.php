<?php

use App\Http\Controllers\Auth\IngresoController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\ProductoFotoController;
use App\Livewire\Clientes\Formulario as ClienteFormulario;
use App\Livewire\Clientes\Listado as ClienteListado;
use App\Livewire\Inicio;
use App\Livewire\Pedidos\Detalle as PedidoDetalle;
use App\Livewire\Pedidos\Formulario as PedidoFormulario;
use App\Livewire\Pedidos\Listado as PedidoListado;
use App\Livewire\Productos\Catalogo;
use App\Livewire\Productos\Formulario;
use App\Livewire\Reportes\Resultado;
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
    Route::livewire('/', Inicio::class)->name('inicio');
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

    Route::livewire('/pedidos', PedidoListado::class)->name('pedidos.listado');
    Route::livewire('/pedidos/nuevo', PedidoFormulario::class)->name('pedidos.nuevo');
    Route::livewire('/pedidos/{pedido}', PedidoDetalle::class)->whereNumber('pedido')->name('pedidos.detalle');
    Route::livewire('/pedidos/{pedido}/editar', PedidoFormulario::class)->whereNumber('pedido')->name('pedidos.editar');
    Route::get('/pedidos/{pedido}/comprobante', ComprobanteController::class)->whereNumber('pedido')->name('pedidos.comprobante');

    Route::livewire('/resultado', Resultado::class)->name('reportes.resultado');
});
