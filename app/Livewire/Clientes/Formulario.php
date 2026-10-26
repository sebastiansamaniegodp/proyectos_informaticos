<?php

namespace App\Livewire\Clientes;

use App\Models\Cliente;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Alta de cliente. La misma pantalla sirve para editar uno existente, que
 * es a donde lleva cada fila del listado.
 */
class Formulario extends Component
{
    public ?Cliente $cliente = null;

    public string $nombre = '';

    public string $documento = '';

    public string $telefono = '';

    public string $direccion_entrega = '';

    public function mount(?Cliente $cliente = null): void
    {
        if (! $cliente?->exists) {
            $this->cliente = null;

            return;
        }

        $this->cliente = $cliente;
        $this->nombre = $cliente->nombre;
        $this->documento = (string) $cliente->documento;
        $this->telefono = (string) $cliente->telefono;
        $this->direccion_entrega = (string) $cliente->direccion_entrega;
    }

    public function updated(string $propiedad): void
    {
        $this->resetErrorBag($propiedad);
    }

    public function guardar(): void
    {
        // Los espacios de más se quitan; un campo vacío se guarda como nulo.
        $limpiar = fn (string $texto) => preg_replace('/\s+/', ' ', trim($texto)) ?: null;

        $datos = Validator::make([
            'nombre' => $limpiar($this->nombre),
            'documento' => $limpiar($this->documento),
            'telefono' => $limpiar($this->telefono),
            'direccion_entrega' => $limpiar($this->direccion_entrega),
        ], [
            'nombre' => ['required', 'string', 'max:120'],
            'documento' => ['nullable', 'string', 'max:20'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion_entrega' => ['nullable', 'string', 'max:200'],
        ], [
            'nombre.required' => 'Escribí el nombre del cliente.',
            'nombre.max' => 'El nombre puede tener hasta 120 letras.',
            'documento.max' => 'El documento puede tener hasta 20 caracteres.',
            'telefono.max' => 'El teléfono puede tener hasta 30 caracteres.',
            'direccion_entrega.max' => 'La dirección puede tener hasta 200 letras.',
        ])->validate();

        $cliente = $this->cliente ?? new Cliente(['activo' => 'S']);
        $cliente->fill($datos)->save();

        $this->redirectRoute('clientes.listado', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.clientes.formulario')
            ->title($this->cliente ? 'Editar cliente' : 'Nuevo cliente');
    }
}
