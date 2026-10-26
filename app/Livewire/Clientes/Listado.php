<?php

namespace App\Livewire\Clientes;

use App\Models\Cliente;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla "Clientes" del prototipo: los clientes activos en orden
 * alfabético, agrupados por la inicial, con búsqueda por nombre o teléfono.
 */
#[Title('Clientes')]
class Listado extends Component
{
    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    /**
     * Clientes que cumplen la búsqueda, agrupados por la inicial del nombre.
     */
    #[Computed]
    public function grupos(): Collection
    {
        return Cliente::activos()
            ->buscar($this->busqueda)
            ->orderByRaw('upper(nombre)')
            ->get()
            ->groupBy(fn (Cliente $cliente) => $cliente->inicial())
            ->sortKeys();
    }

    public function render(): View
    {
        return view('livewire.clientes.listado');
    }
}
