<?php

namespace App\Livewire\Stock;

use App\Models\MovimientoStock;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Stock de un producto: el disponible, los rollos abiertos con sus metros y
 * los últimos movimientos. Desde acá se carga un ingreso o un ajuste.
 */
#[Title('Stock del producto')]
class Detalle extends Component
{
    /** Movimientos que se muestran; el historial completo queda en la base. */
    private const MOVIMIENTOS = 30;

    public Producto $producto;

    /**
     * Las cifras de VW_STOCK_DISPONIBLE (RN-07). La vista deja afuera los
     * productos dados de baja: para esos todo queda en cero.
     */
    #[Computed]
    public function resumen(): object
    {
        return DB::table('vw_stock_disponible')->where('id_producto', $this->producto->id_producto)->first()
            ?? (object) ['piezas_cerradas' => 0, 'rollos_abiertos' => 0, 'metros_sueltos' => 0, 'disponible_total' => 0];
    }

    /**
     * Rollos abiertos en el orden en que se cortan: primero el de menos
     * metros (RN-09, SUP-03).
     */
    #[Computed]
    public function rollos(): Collection
    {
        return $this->producto->rollosAbiertos()
            ->orderBy('metros_restantes')
            ->orderBy('id_rollo')
            ->get();
    }

    #[Computed]
    public function movimientos(): Collection
    {
        return MovimientoStock::where('id_producto', $this->producto->id_producto)
            ->orderByDesc('fecha')
            ->orderByDesc('id_movimiento')
            ->limit(self::MOVIMIENTOS)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.stock.detalle');
    }
}
