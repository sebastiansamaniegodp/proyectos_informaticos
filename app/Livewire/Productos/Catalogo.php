<?php

namespace App\Livewire\Productos;

use App\Models\Familia;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla "Catálogo" del prototipo: los productos activos agrupados por
 * familia, con búsqueda por nombre o código y filtro por familia.
 */
#[Title('Catálogo')]
class Catalogo extends Component
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
     * Productos que cumplen la búsqueda y el filtro, agrupados por familia en
     * el orden de las familias.
     */
    #[Computed]
    public function grupos(): Collection
    {
        $busqueda = trim($this->busqueda);

        $productos = Producto::activos()
            ->when($this->familia, fn ($query) => $query->where('id_familia', $this->familia))
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $patron = '%'.addcslashes($busqueda, '%_\\').'%';
                $query->where(fn ($query) => $query
                    ->where('descripcion', 'ilike', $patron)
                    ->orWhere('codigo', 'ilike', $patron));
            })
            ->orderBy('altura_cm', 'desc')
            ->orderBy('codigo')
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
        return view('livewire.productos.catalogo');
    }
}
