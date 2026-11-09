<?php

namespace App\Livewire\Usuarios;

use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pantalla "Usuarios": todos los que pueden entrar al sistema, con su rol.
 * Los desactivados quedan en la misma lista, marcados, porque son pocos.
 * Solo la ve el administrador (permiso "usuarios").
 */
#[Title('Usuarios')]
class Listado extends Component
{
    #[Computed]
    public function usuarios(): Collection
    {
        return Usuario::orderByDesc('activo')->orderByRaw('upper(nombre)')->get();
    }

    public function render(): View
    {
        return view('livewire.usuarios.listado');
    }
}
