<?php

namespace App\Livewire\Stock;

use App\Models\Familia;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla "Stock": lo disponible de cada producto activo, agrupado por
 * familia, con la misma búsqueda y filtro que el catálogo. La cuenta del
 * disponible la hace la vista VW_STOCK_DISPONIBLE (RN-07).
 */
#[Title('Stock')]
class Listado extends Component
{
    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    #[Url(except: null)]
    public ?int $familia = null;

    /**
     * Tocar la familia elegida otra vez quita el filtro.
     */
    public function filtrar(int $idFamilia): void
    {
        $this->familia = $this->familia === $idFamilia ? null : $idFamilia;
    }

    #[Computed]
    public function familias(): Collection
    {
        return Familia::activas()->get();
    }

    /**
     * Productos que cumplen la búsqueda y el filtro, con las columnas de la
     * vista de stock, agrupados por familia en el orden de las familias.
     */
    #[Computed]
    public function grupos(): Collection
    {
        $busqueda = trim($this->busqueda);

        $productos = Producto::activos()
            ->join('vw_stock_disponible as disponible', 'disponible.id_producto', '=', 'producto.id_producto')
            ->select('producto.*', 'disponible.piezas_cerradas', 'disponible.rollos_abiertos', 'disponible.metros_sueltos', 'disponible.disponible_total')
            ->when($this->familia, fn ($query) => $query->where('producto.id_familia', $this->familia))
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $patron = '%'.addcslashes($busqueda, '%_\\').'%';
                $query->where(fn ($query) => $query
                    ->where('producto.descripcion', 'ilike', $patron)
                    ->orWhere('producto.codigo', 'ilike', $patron));
            })
            ->orderBy('producto.altura_cm', 'desc')
            ->orderBy('producto.codigo')
            ->get()
            ->groupBy('id_familia');

        return $this->familias
            ->filter(fn (Familia $familia) => $productos->has($familia->id_familia))
            ->map(fn (Familia $familia) => [
                'familia' => $familia,
                'productos' => $productos->get($familia->id_familia),
            ])
            ->values();
    }

    public function render(): View
    {
        return view('livewire.stock.listado');
    }
}
