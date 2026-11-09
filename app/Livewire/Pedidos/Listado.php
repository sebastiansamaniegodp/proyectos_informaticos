<?php

namespace App\Livewire\Pedidos;

use App\Models\Pedido;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pantalla "Pedidos" del prototipo. Arranca en los pendientes, que es el
 * reporte que la dueña pidió ver todas las mañanas: los pedidos en
 * borrador y los confirmados sin entregar (RN-26, REP-07).
 */
#[Title('Pedidos')]
class Listado extends Component
{
    use WithPagination;

    /** Pestañas del listado y los estados que muestra cada una. */
    public const FILTROS = [
        'pendientes' => ['Pendientes', Pedido::PENDIENTES],
        'entregados' => ['Entregados', ['ENTREGADO']],
        'cobrados' => ['Cobrados', ['COBRADO']],
        'anulados' => ['Anulados', ['ANULADO']],
        'todos' => ['Todos', null],
    ];

    #[Url(except: 'pendientes')]
    public string $filtro = 'pendientes';

    #[Url(as: 'q', except: '')]
    public string $busqueda = '';

    public function mount(): void
    {
        if (! array_key_exists($this->filtro, self::FILTROS)) {
            $this->filtro = 'pendientes';
        }
    }

    public function filtrar(string $filtro): void
    {
        if (array_key_exists($filtro, self::FILTROS)) {
            $this->filtro = $filtro;
            $this->resetPage();
        }
    }

    public function updatedBusqueda(): void
    {
        $this->resetPage();
    }

    /**
     * Cuántos pedidos hay en cada pestaña, para el número al lado del nombre.
     */
    #[Computed]
    public function cantidades(): Collection
    {
        $porEstado = Pedido::query()->selectRaw('estado, count(*) as cantidad')->groupBy('estado')->pluck('cantidad', 'estado');

        return collect(self::FILTROS)->map(fn (array $filtro) => $filtro[1] === null
            ? $porEstado->sum()
            : collect($filtro[1])->sum(fn (string $estado) => $porEstado[$estado] ?? 0));
    }

    #[Computed]
    public function pedidos(): Paginator
    {
        $estados = self::FILTROS[$this->filtro][1];

        return Pedido::with(['cliente', 'lineas.producto.familia'])
            ->when($estados, fn ($query) => $query->enEstado($estados))
            ->when(trim($this->busqueda) !== '', fn ($query) => $query->whereHas('cliente', fn ($query) => $query->buscar($this->busqueda)))
            ->orderByDesc('fecha_pedido')
            ->orderByDesc('id_pedido')
            ->simplePaginate(25);
    }

    public function render(): View
    {
        return view('livewire.pedidos.listado');
    }
}
