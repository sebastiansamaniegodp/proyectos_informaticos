<?php

namespace App\Livewire\Stock;

use App\Exceptions\ErrorDeStock;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Services\StockService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Entrada de mercadería: piezas cerradas nuevas, por carga inicial o por
 * reposición (RN-35).
 */
#[Title('Ingreso de stock')]
class Ingreso extends Component
{
    public Producto $producto;

    public string $motivo = 'REPOSICION';

    public string $cantidad = '';

    public string $observacion = '';

    public function mount(Producto $producto): void
    {
        $this->producto = $producto;

        // Lo primero que entra de un producto es su carga inicial.
        if (! $producto->movimientos()->exists()) {
            $this->motivo = 'CARGA_INICIAL';
        }
    }

    public function updated(string $propiedad): void
    {
        $this->resetErrorBag($propiedad);
    }

    public function guardar(StockService $stock): void
    {
        $datos = Validator::make([
            'motivo' => $this->motivo,
            'cantidad' => trim($this->cantidad),
            'observacion' => trim($this->observacion) ?: null,
        ], [
            'motivo' => ['required', Rule::in(array_keys(MovimientoStock::MOTIVOS_INGRESO))],
            'cantidad' => ['required', 'integer', 'between:1,99999'],
            'observacion' => ['nullable', 'string', 'max:200'],
        ], [
            'motivo.*' => 'Elegí si es la carga inicial o una reposición.',
            'cantidad.*' => 'Escribí cuántos '.$this->producto->nombrePiezas().' entran, en números enteros.',
            'observacion.max' => 'La observación puede tener hasta 200 letras.',
        ])->validate();

        try {
            $stock->ingresar($this->producto, (int) $datos['cantidad'], $datos['motivo'], $datos['observacion']);
        } catch (ErrorDeStock $error) {
            $this->addError('cantidad', $error->getMessage());

            return;
        }

        session()->flash('aviso', 'Ingresaron '.Producto::cantidadConUnidad((int) $datos['cantidad'], $this->producto->unidad_venta).'.');

        $this->redirectRoute('stock.detalle', $this->producto, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.stock.ingreso');
    }
}
