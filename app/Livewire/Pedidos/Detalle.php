<?php

namespace App\Livewire\Pedidos;

use App\Exceptions\ErrorDePedido;
use App\Models\MovimientoStock;
use App\Models\Pedido;
use App\Services\PedidoService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Un pedido con sus líneas, fechas y totales ("Pedido confirmado" del
 * prototipo). Desde acá se avanza de estado y se manda el comprobante.
 */
class Detalle extends Component
{
    public Pedido $pedido;

    /** Aviso de la última acción hecha en esta pantalla. */
    public ?string $aviso = null;

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
        $this->aviso = session('aviso');
    }

    /**
     * Lo que movió el pedido en el stock: las ventas al confirmar y, si se
     * anuló, las reposiciones (RN-15).
     */
    #[Computed]
    public function movimientos(): Collection
    {
        return MovimientoStock::with('producto.familia')
            ->where('id_pedido', $this->pedido->id_pedido)
            ->orderBy('id_movimiento')
            ->get();
    }

    public function confirmar(PedidoService $pedidos): void
    {
        $this->avanzar(fn () => $pedidos->confirmar($this->pedido), 'Pedido confirmado. El stock ya se descontó.');
    }

    public function marcarEntregado(PedidoService $pedidos): void
    {
        $this->avanzar(fn () => $pedidos->marcarEntregado($this->pedido), 'Pedido marcado como entregado. Falta registrar el cobro.');
    }

    public function registrarCobro(PedidoService $pedidos): void
    {
        $this->avanzar(fn () => $pedidos->registrarCobro($this->pedido), 'Cobro registrado. El pedido ya suma en el resultado del mes.');
    }

    public function anular(PedidoService $pedidos): void
    {
        $devuelve = $this->pedido->estado !== 'BORRADOR';

        $this->avanzar(fn () => $pedidos->anular($this->pedido), $devuelve
            ? 'Pedido anulado. Lo que había salido volvió al stock.'
            : 'Pedido anulado.');
    }

    private function avanzar(callable $operacion, string $aviso): void
    {
        $this->aviso = null;

        try {
            $this->pedido = $operacion();
        } catch (ErrorDePedido $error) {
            $this->pedido->refresh();
            $this->addError('estado', $error->getMessage());

            return;
        }

        unset($this->movimientos);
        $this->aviso = $aviso;
    }

    public function render(): View
    {
        $this->pedido->loadMissing(['cliente', 'lineas.producto.familia']);

        return view('livewire.pedidos.detalle')->title('Pedido N° '.$this->pedido->numero());
    }
}
