<?php

namespace App\Livewire\Stock;

use App\Exceptions\ErrorDeStock;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Services\StockService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Ajuste manual de stock (DD-07, PR-03): toda salida que no es venta, con
 * motivo obligatorio (RN-30). Una diferencia de inventario también puede
 * sumar, cuando el conteo da más de lo registrado.
 *
 * En los tejidos que se cortan se ajustan piezas cerradas o metros de un
 * rollo abierto.
 */
#[Title('Ajuste de stock')]
class Ajuste extends Component
{
    public Producto $producto;

    // Sin valor de entrada: el motivo se elige siempre a mano (RN-30).
    public string $motivo = '';

    /** SALE o ENTRA. Solo una diferencia de inventario puede entrar. */
    public string $sentido = 'SALE';

    /** PIEZAS o METROS. */
    public string $que = 'PIEZAS';

    /** Rollo al que va el ajuste en metros; vacío, el que tocaría en una venta. */
    public string $id_rollo = '';

    public string $cantidad = '';

    public string $observacion = '';

    public function mount(Producto $producto): void
    {
        $this->producto = $producto;
    }

    public function updated(string $propiedad): void
    {
        $this->resetErrorBag($propiedad);

        if ($propiedad === 'motivo' && $this->motivo !== 'INVENTARIO') {
            $this->sentido = 'SALE';
        }

        // Al cambiar entre piezas y metros, la cantidad escrita ya no vale.
        if ($propiedad === 'que') {
            $this->id_rollo = '';
            $this->resetErrorBag(['cantidad', 'id_rollo']);
        }
    }

    #[Computed]
    public function rollos(): Collection
    {
        return $this->producto->rollosAbiertos()
            ->orderBy('metros_restantes')
            ->orderBy('id_rollo')
            ->get();
    }

    public function enMetros(): bool
    {
        return $this->producto->seCorta() && $this->que === 'METROS';
    }

    public function guardar(StockService $stock): void
    {
        $enMetros = $this->enMetros();
        $entra = $this->motivo === 'INVENTARIO' && $this->sentido === 'ENTRA';

        $datos = Validator::make([
            'motivo' => $this->motivo,
            'cantidad' => trim($this->cantidad),
            'id_rollo' => $enMetros && $this->id_rollo !== '' ? (int) $this->id_rollo : null,
            'observacion' => trim($this->observacion) ?: null,
        ], [
            'motivo' => ['required', Rule::in(array_keys(MovimientoStock::MOTIVOS_AJUSTE))],
            'cantidad' => $enMetros
                ? ['required', 'regex:/^\d{1,4}([.,]\d{1,2})?$/', 'not_regex:/^0+([.,]0+)?$/']
                : ['required', 'integer', 'between:1,99999'],
            'id_rollo' => [
                Rule::requiredIf($enMetros && $entra),
                'nullable',
                Rule::in($this->rollos->pluck('id_rollo')),
            ],
            'observacion' => ['nullable', 'string', 'max:200'],
        ], [
            'motivo.*' => 'Elegí el motivo del ajuste.',
            'cantidad.*' => $enMetros
                ? 'Escribí los metros, por ejemplo 2 o 1,5.'
                : 'Escribí cuántos '.$this->producto->nombrePiezas().', en números enteros.',
            'id_rollo.required' => 'Elegí a qué rollo se le suman los metros.',
            'id_rollo.in' => 'Ese rollo ya no está abierto. Elegí otro.',
            'observacion.max' => 'La observación puede tener hasta 200 letras.',
        ])->validate();

        $signo = $entra ? 1 : -1;

        try {
            if ($enMetros) {
                $rollo = $datos['id_rollo'] ? $this->rollos->firstWhere('id_rollo', $datos['id_rollo']) : null;
                $metros = (float) str_replace(',', '.', $datos['cantidad']);
                $movimiento = $stock->ajustarMetros($this->producto, $signo * $metros, $datos['motivo'], $rollo, $datos['observacion']);
            } else {
                $movimiento = $stock->ajustarPiezas($this->producto, $signo * (int) $datos['cantidad'], $datos['motivo'], $datos['observacion']);
            }
        } catch (ErrorDeStock $error) {
            $this->addError('cantidad', $error->getMessage());

            return;
        }

        $rollo = $movimiento->id_rollo ? " del rollo #{$movimiento->id_rollo}" : '';
        session()->flash('aviso', 'Ajuste guardado: '.$movimiento->cantidadConSigno().$rollo.'.');

        $this->redirectRoute('stock.detalle', $this->producto, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.stock.ajuste');
    }
}
