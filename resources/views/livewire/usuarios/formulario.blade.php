@php
    use App\Enums\Rol;
    use App\Livewire\Usuarios\Formulario;

    $etiqueta = 'mb-1.5 block text-[13px] font-medium text-[#44443F]';
    $campo = 'min-h-12 w-full rounded-[10px] border border-[#D5D5CF] bg-white px-3 text-[15px] text-[#141413] outline-none focus:border-[#141413] aria-invalid:border-[#B3261E] disabled:bg-[#F4F4F1] disabled:text-[#5A5A54]';
    $tarjeta = 'flex flex-col gap-3 rounded-[10px] border border-[#E3E3DE] bg-white px-4 py-3.5';
    $error = 'mt-1.5 text-[13px] leading-normal text-[#B3261E]';
    $ayuda = 'mt-1.5 text-[13px] leading-normal text-[#5A5A54]';

    // Lo que puede hacer cada rol, para elegir sin tener que acordarse.
    $descripciones = [
        'ADMIN' => 'Entra a todo: Inicio, pedidos, clientes, stock, catálogo, resultado del mes y usuarios.',
        'VENDEDOR' => 'Carga pedidos y clientes. Ve el stock y el catálogo, sin modificarlos.',
        'DEPOSITO' => 'Registra ingresos y ajustes de stock. Ve el catálogo, sin modificarlo.',
    ];

    $propio = $this->esPropio();
    $tituloPantalla = $usuario ? 'Editar usuario' : 'Nuevo usuario';
@endphp

<form wire:submit="guardar" novalidate
    class="flex max-w-[640px] flex-1 flex-col px-4 pt-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    <x-encabezado :titulo="$tituloPantalla"
        :migas="[['Inicio', route('inicio')], ['Usuarios', route('usuarios.listado')], [$tituloPantalla]]" />

    @error('estado')
        <div role="alert" class="mb-3.5 rounded-[10px] border border-[#B3261E]/30 bg-[#B3261E]/8 px-4 py-3 text-sm font-medium text-[#B3261E]">{{ $message }}</div>
    @enderror

    <div class="mb-4 flex flex-col gap-3.5 sm:mb-0 md:gap-4">

        <div class="{{ $tarjeta }}">
            <div>
                <label for="nombre" class="{{ $etiqueta }}">Nombre</label>
                <input id="nombre" type="text" maxlength="80" autocomplete="off" wire:model="nombre"
                    @unless ($usuario) autofocus @endunless
                    @error('nombre') aria-invalid="true" @enderror class="{{ $campo }}">
                @error('nombre') <div class="{{ $error }}">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="{{ $tarjeta }}">
            <div class="flex flex-col gap-3 sm:flex-row sm:gap-2.5">
                <div class="min-w-0 flex-1">
                    <label for="username" class="{{ $etiqueta }}">Usuario</label>
                    <input id="username" type="text" maxlength="30" autocomplete="off" autocapitalize="none" spellcheck="false" wire:model="username"
                        @error('username') aria-invalid="true" @enderror class="{{ $campo }}">
                    @error('username') <div class="{{ $error }}">{{ $message }}</div> @enderror
                </div>
                <div class="min-w-0 flex-1">
                    <label for="clave" class="{{ $etiqueta }}">{{ $usuario ? 'Contraseña nueva' : 'Contraseña' }}</label>
                    <input id="clave" type="password" maxlength="100" autocomplete="new-password" wire:model="clave"
                        @error('clave') aria-invalid="true" @enderror class="{{ $campo }}">
                    @error('clave')
                        <div class="{{ $error }}">{{ $message }}</div>
                    @else
                        <div class="{{ $ayuda }}">
                            @if ($usuario) Dejala vacía para no cambiarla. @else Al menos {{ Formulario::CLAVE_MINIMA }} caracteres. @endif
                        </div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="{{ $tarjeta }}">
            <div>
                <label for="rol" class="{{ $etiqueta }}">Rol</label>
                <select id="rol" wire:model.live="rol" @disabled($propio)
                    @error('rol') aria-invalid="true" @enderror class="{{ $campo }}">
                    @foreach (Rol::cases() as $opcion)
                        <option value="{{ $opcion->value }}">{{ $opcion->etiqueta() }}</option>
                    @endforeach
                </select>
                @error('rol')
                    <div class="{{ $error }}">{{ $message }}</div>
                @else
                    <div class="{{ $ayuda }}">
                        {{ $descripciones[$rol] ?? '' }}
                        @if ($propio) No podés cambiar tu propio rol. @endif
                    </div>
                @enderror
            </div>
        </div>

    </div>

    {{-- En el celular el botón queda fijo abajo; desde tablet, a la derecha al final. --}}
    <div class="sticky bottom-0 -mx-4 mt-auto border-t border-[#E3E3DE] bg-white px-4 pt-3 pb-4 sm:static sm:mx-0 sm:mt-4 sm:flex sm:justify-end sm:gap-2.5 sm:border-0 sm:bg-transparent sm:p-0 lg:mt-5">
        <a href="{{ route('usuarios.listado') }}" wire:navigate
            class="hidden min-h-11 items-center rounded-[10px] border border-[#D5D5CF] bg-white px-5 text-[15px] font-semibold text-[#141413] no-underline hover:bg-[#FAFAF8] sm:inline-flex">
            Cancelar
        </a>
        <button type="submit" wire:loading.attr="disabled" wire:target="guardar"
            class="flex min-h-[52px] w-full cursor-pointer items-center justify-center rounded-[10px] border-0 bg-[#141413] text-base font-semibold text-white hover:bg-[#2A2A28] disabled:cursor-default disabled:bg-[#5A5A54] sm:min-h-11 sm:w-auto sm:px-6 sm:text-[15px]">
            <span wire:loading.remove wire:target="guardar">Guardar usuario</span>
            <span wire:loading wire:target="guardar">Guardando…</span>
        </button>

        @if ($usuario && ! $propio)
            {{-- Desactivar no borra: la persona deja de poder entrar. --}}
            @php $activo = $usuario->activo === 'S'; @endphp
            <button type="button" wire:click="cambiarEstado"
                @if ($activo) wire:confirm="¿Desactivar a {{ $usuario->nombre }}? Ya no va a poder entrar. Se puede volver a activar." @endif
                class="mt-1 min-h-11 w-full cursor-pointer border-0 bg-transparent p-0 text-sm font-medium text-[#5A5A54] hover:text-[#141413] hover:underline sm:order-first sm:mt-0 sm:mr-auto sm:w-auto">
                {{ $activo ? 'Desactivar' : 'Volver a activar' }}
            </button>
        @endif
    </div>

</form>
