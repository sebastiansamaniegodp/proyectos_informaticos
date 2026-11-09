@php
    // En pantalla grande cada fila pasa a columnas: nombre, usuario, rol y estado.
    $columnas = 'lg:grid lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(120px,160px)_minmax(110px,140px)] lg:items-center lg:gap-x-4 lg:px-5';
@endphp

<div class="flex max-w-[1200px] flex-1 flex-col px-4 pt-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    <x-encabezado titulo="Usuarios" :migas="[['Inicio', route('inicio')], ['Usuarios']]" />

    @if (session('aviso'))
        <div role="status" class="mb-3.5 rounded-[10px] border border-[#2F6B45]/30 bg-[#2F6B45]/8 px-4 py-3 text-sm font-medium text-[#2F6B45]">
            {{ session('aviso') }}
        </div>
    @endif

    <div class="hidden justify-end sm:flex">
        <a href="{{ route('usuarios.nuevo') }}" wire:navigate
            class="inline-flex min-h-11 items-center gap-2 rounded-[10px] bg-[#141413] px-4 text-[15px] font-semibold text-white no-underline hover:bg-[#2A2A28]">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo usuario
        </a>
    </div>

    <div class="mb-4 flex flex-col gap-4 sm:mt-4 sm:mb-0">
        <section class="overflow-hidden rounded-[10px] border border-[#E3E3DE] bg-white">
            <div class="hidden py-2.5 text-xs font-medium tracking-[0.04em] text-[#5A5A54] uppercase {{ $columnas }}" aria-hidden="true">
                <span>Nombre</span>
                <span>Usuario</span>
                <span>Rol</span>
                <span>Estado</span>
            </div>

            <ul class="m-0 list-none p-0">
                @foreach ($this->usuarios as $usuario)
                    @php $activo = $usuario->activo === 'S'; @endphp
                    <li wire:key="usuario-{{ $usuario->id_usuario }}">
                        <a href="{{ route('usuarios.editar', $usuario) }}" wire:navigate
                            @class([
                                'block border-t border-[#EDEDE8] px-4 py-3 text-inherit no-underline hover:bg-[#FAFAF8]',
                                'max-lg:border-t-0' => $loop->first,
                                $columnas,
                            ])>
                            <span class="block text-[15px] font-medium {{ $activo ? '' : 'text-[#8A8A84]' }}">
                                {{ $usuario->nombre }}
                                @if ($usuario->is(auth()->user())) <span class="font-normal text-[#5A5A54]">(vos)</span> @endif
                            </span>
                            {{-- En el celular el usuario, el rol y el estado van en una sola línea abajo del nombre. --}}
                            <span class="mt-0.5 block text-[13px] text-[#5A5A54] lg:hidden">
                                {{ $usuario->username }} · {{ $usuario->rol->etiqueta() }}@unless ($activo) · Desactivado @endunless
                            </span>
                            <span class="hidden text-sm text-[#44443F] lg:block">{{ $usuario->username }}</span>
                            <span class="hidden text-sm text-[#44443F] lg:block">{{ $usuario->rol->etiqueta() }}</span>
                            <span class="hidden text-sm lg:block {{ $activo ? 'text-[#2F6B45]' : 'text-[#8A8A84]' }}">{{ $activo ? 'Activo' : 'Desactivado' }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    {{-- En el celular el botón queda abajo, a mano del pulgar. --}}
    <div class="sticky bottom-0 -mx-4 mt-auto border-t border-[#E3E3DE] bg-white px-4 pt-3 pb-4 sm:hidden">
        <a href="{{ route('usuarios.nuevo') }}" wire:navigate
            class="flex min-h-[52px] items-center justify-center gap-2 rounded-[10px] bg-[#141413] text-base font-semibold text-white no-underline hover:bg-[#2A2A28]">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo usuario
        </a>
    </div>

</div>
