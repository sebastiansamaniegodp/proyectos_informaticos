<?php

namespace App\Livewire\Clientes;

use App\Models\Cliente;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
        // Los espacios de más se quitan; un campo con solo espacios cuenta como vacío.
        $limpiar = fn (string $texto) => preg_replace('/\s+/', ' ', trim($texto)) ?: null;

        $datos = Validator::make([
            'nombre' => $limpiar($this->nombre),
            // La cédula se suele escribir con puntos (4.567.890): se guardan solo los dígitos y el guion.
            'documento' => $limpiar(str_replace(['.', ' '], '', $this->documento)),
            'telefono' => $limpiar($this->telefono),
            'direccion_entrega' => $limpiar($this->direccion_entrega),
        ], [
            'nombre' => ['required', 'string', 'min:2', 'max:120', 'regex:/\p{L}/u'],
            // Cédula: solo dígitos. RUC: dígitos, guion y el dígito verificador.
            'documento' => [
                'required', 'string', 'max:20', 'regex:/^\d{5,10}(-\d)?$/',
                Rule::unique('cliente', 'documento')->ignore($this->cliente?->id_cliente, 'id_cliente'),
            ],
            'telefono' => ['required', 'string', 'max:30', 'regex:/^\+?[\d\s()-]+$/', function ($atributo, $valor, $fallar) {
                $digitos = strlen(preg_replace('/\D/', '', $valor));

                if ($digitos < 6 || $digitos > 15) {
                    $fallar('El teléfono tiene que tener entre 6 y 15 números, por ejemplo 0981 234 567.');
                }
            }],
            'direccion_entrega' => ['required', 'string', 'min:3', 'max:200'],
        ], [
            'nombre.required' => 'Escribí el nombre del cliente.',
            'nombre.min' => 'El nombre tiene que tener al menos 2 letras.',
            'nombre.regex' => 'El nombre tiene que tener letras, no solo números.',
            'documento.required' => 'Escribí la cédula o el RUC del cliente.',
            'documento.regex' => 'La cédula va solo con números (4567890); el RUC, con guion y dígito verificador (80012345-6).',
            'documento.unique' => 'Ya hay un cliente con ese documento.',
            'telefono.required' => 'Escribí el teléfono del cliente.',
            'telefono.regex' => 'El teléfono va solo con números, por ejemplo 0981 234 567.',
            'direccion_entrega.required' => 'Escribí la dirección de entrega.',
            'direccion_entrega.min' => 'Escribí la dirección completa.',
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
