<?php

namespace App\Livewire\Usuarios;

use App\Enums\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Alta de usuario. La misma pantalla sirve para editar uno existente:
 * cambiarle el nombre, el rol o la contraseña, y desactivarlo.
 *
 * El administrador no puede cambiarse el rol ni desactivarse a sí mismo.
 * Así siempre queda al menos un administrador activo que pueda entrar a
 * esta pantalla.
 */
class Formulario extends Component
{
    /** Corta a propósito: es un sistema de prueba y se usan claves como 123. */
    public const CLAVE_MINIMA = 3;

    public ?Usuario $usuario = null;

    public string $nombre = '';

    public string $username = '';

    public string $rol = 'VENDEDOR';

    /** En la edición, vacía significa "no cambiar la contraseña". */
    public string $clave = '';

    public function mount(?Usuario $usuario = null): void
    {
        if (! $usuario?->exists) {
            $this->usuario = null;

            return;
        }

        $this->usuario = $usuario;
        $this->nombre = $usuario->nombre;
        $this->username = $usuario->username;
        $this->rol = $usuario->rol->value;
    }

    public function updated(string $propiedad): void
    {
        $this->resetErrorBag($propiedad);
    }

    /** El usuario que se está editando es el mismo que tiene la sesión. */
    public function esPropio(): bool
    {
        return $this->usuario !== null && $this->usuario->is(auth()->user());
    }

    public function guardar(): void
    {
        $datos = Validator::make([
            'nombre' => preg_replace('/\s+/', ' ', trim($this->nombre)) ?: null,
            // El usuario se guarda en minúsculas, así no importa cómo se escriba al crearlo.
            'username' => mb_strtolower(trim($this->username)) ?: null,
            'rol' => $this->rol,
            'clave' => $this->clave !== '' ? $this->clave : null,
        ], [
            'nombre' => ['required', 'string', 'min:2', 'max:80', 'regex:/\p{L}/u'],
            'username' => [
                'required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/',
                Rule::unique('usuario', 'username')->ignore($this->usuario?->id_usuario, 'id_usuario'),
            ],
            'rol' => ['required', Rule::enum(Rol::class), function ($atributo, $valor, $fallar) {
                if ($this->esPropio() && $valor !== $this->usuario->rol->value) {
                    $fallar('No podés cambiar tu propio rol. Pedile a otro administrador que lo haga.');
                }
            }],
            'clave' => [$this->usuario ? 'nullable' : 'required', 'string', 'min:'.self::CLAVE_MINIMA, 'max:100'],
        ], [
            'nombre.required' => 'Escribí el nombre de la persona.',
            'nombre.min' => 'El nombre tiene que tener al menos 2 letras.',
            'nombre.max' => 'El nombre puede tener hasta 80 letras.',
            'nombre.regex' => 'El nombre tiene que tener letras, no solo números.',
            'username.required' => 'Escribí el usuario con el que va a entrar.',
            'username.min' => 'El usuario tiene que tener al menos 3 letras.',
            'username.max' => 'El usuario puede tener hasta 30 letras.',
            'username.regex' => 'El usuario va sin espacios ni acentos: solo letras, números, punto y guion.',
            'username.unique' => 'Ya hay alguien con ese usuario.',
            'rol.required' => 'Elegí un rol.',
            'rol.enum' => 'Elegí un rol de la lista.',
            'clave.required' => 'Escribí una contraseña.',
            'clave.min' => 'La contraseña tiene que tener al menos '.self::CLAVE_MINIMA.' caracteres.',
            'clave.max' => 'La contraseña es demasiado larga.',
        ])->validate();

        $usuario = $this->usuario ?? new Usuario(['activo' => 'S']);
        $usuario->fill([
            'nombre' => $datos['nombre'],
            'username' => $datos['username'],
            'rol' => $datos['rol'],
        ]);

        if ($datos['clave'] !== null) {
            $usuario->clave_hash = $datos['clave'];
        }

        $usuario->save();

        session()->flash('aviso', $this->usuario
            ? "Se guardaron los cambios de {$usuario->nombre}."
            : "Se creó el usuario {$usuario->username}.");

        $this->redirectRoute('usuarios.listado', navigate: true);
    }

    /**
     * Desactivar: la persona deja de poder entrar, pero el usuario no se
     * borra. Con la misma acción se vuelve a activar.
     */
    public function cambiarEstado(): void
    {
        if (! $this->usuario) {
            return;
        }

        if ($this->esPropio()) {
            $this->addError('estado', 'No podés desactivar tu propio usuario.');

            return;
        }

        $activo = $this->usuario->activo === 'S';
        $this->usuario->update(['activo' => $activo ? 'N' : 'S']);

        session()->flash('aviso', $activo
            ? "Se desactivó a {$this->usuario->nombre}. Ya no puede entrar al sistema."
            : "Se volvió a activar a {$this->usuario->nombre}.");

        $this->redirectRoute('usuarios.listado', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.usuarios.formulario')
            ->title($this->usuario ? 'Editar usuario' : 'Nuevo usuario');
    }
}
