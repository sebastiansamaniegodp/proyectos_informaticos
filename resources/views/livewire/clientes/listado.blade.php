@php
    // En pantalla grande cada fila pasa a columnas: nombre, teléfono, dirección y documento.
    $columnas = 'lg:grid lg:grid-cols-[minmax(0,1.3fr)_minmax(130px,170px)_minmax(0,1.4fr)_minmax(110px,140px)] lg:items-center lg:gap-x-4 lg:px-5';
@endphp

<div class="flex max-w-[1200px] flex-1 flex-col px-4 pt-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    <x-encabezado titulo="Clientes" :migas="[['Inicio', route('inicio')], ['Clientes']]" />

    <div class="flex flex-wrap items-center justify-between gap-2.5">
        <label for="busqueda" class="sr-only">Buscar cliente</label>
        <input id="busqueda" type="search" wire:model.live.debounce.300ms="busqueda"
            placeholder="Buscar por nombre o teléfono" autocomplete="off"
            class="min-h-11 w-full rounded-[10px] border border-[#D5D5CF] bg-white px-3.5 text-[15px] text-[#141413] outline-none focus:border-[#141413] sm:w-[300px]">

        <a href="{{ route('clientes.nuevo') }}" wire:navigate
            class="hidden min-h-11 items-center gap-2 rounded-[10px] bg-[#141413] px-4 text-[15px] font-semibold text-white no-underline hover:bg-[#2A2A28] sm:inline-flex">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo cliente
        </a>
    </div>

    <div class="mt-4 mb-4 flex flex-col gap-4 sm:mb-0">
        @if ($this->grupos->isNotEmpty())
            <section class="overflow-hidden rounded-[10px] border border-[#E3E3DE] bg-white">
                <div class="hidden py-2.5 text-xs font-medium tracking-[0.04em] text-[#5A5A54] uppercase {{ $columnas }}" aria-hidden="true">
                    <span>Nombre</span>
                    <span>Teléfono</span>
                    <span>Dirección de entrega</span>
                    <span>Documento</span>
                </div>

                @foreach ($this->grupos as $inicial => $clientes)
                    {{-- La primera letra no lleva línea arriba en el celular, donde no hay fila de títulos. --}}
                    <h2 wire:key="inicial-{{ $inicial }}"
                        @class([
                            'm-0 border-t border-[#EDEDE8] bg-[#FAFAF8] px-4 pt-2.5 pb-1.5 text-xs font-semibold tracking-[0.06em] text-[#5A5A54] uppercase lg:px-5',
                            'max-lg:border-t-0' => $loop->first,
                        ])>
                        {{ $inicial }}
                    </h2>
                    <ul class="m-0 list-none p-0">
                        @foreach ($clientes as $cliente)
                            <li wire:key="cliente-{{ $cliente->id_cliente }}">
                                <a href="{{ route('clientes.editar', $cliente) }}" wire:navigate
                                    class="block border-t border-[#EDEDE8] px-4 py-3 text-inherit no-underline hover:bg-[#FAFAF8] {{ $columnas }}">
                                    <span class="block text-[15px] font-medium">{{ $cliente->nombre }}</span>
                                    {{-- En el celular el teléfono y la dirección van en una sola línea abajo del nombre. --}}
                                    <span class="mt-0.5 block text-[13px] text-[#5A5A54] lg:hidden">{{ $cliente->contacto() }}</span>
                                    <span class="hidden text-sm lg:block {{ $cliente->telefono ? '' : 'text-[#A8A8A2]' }}">{{ $cliente->telefono ?: 'Sin teléfono' }}</span>
                                    <span class="hidden truncate text-sm text-[#44443F] lg:block">{{ $cliente->direccion_entrega }}</span>
                                    <span class="hidden text-sm text-[#44443F] lg:block">{{ $cliente->documento }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </section>
        @else
            <div class="rounded-[10px] border border-[#E3E3DE] bg-white px-4 py-8 text-center text-sm leading-normal text-[#5A5A54]">
                @if (trim($busqueda) !== '')
                    No hay clientes que coincidan con la búsqueda.
                @else
                    Todavía no hay clientes cargados.
                @endif
            </div>
        @endif

        <p class="m-0 text-[13px] leading-normal text-[#5A5A54]">
            Para dar de alta un cliente alcanza con el nombre. El teléfono y la dirección se pueden cargar después.
        </p>
    </div>

    {{-- En el celular el botón queda abajo, a mano del pulgar, como en el prototipo. --}}
    <div class="sticky bottom-0 -mx-4 mt-auto border-t border-[#E3E3DE] bg-white px-4 pt-3 pb-4 sm:hidden">
        <a href="{{ route('clientes.nuevo') }}" wire:navigate
            class="flex min-h-[52px] items-center justify-center gap-2 rounded-[10px] bg-[#141413] text-base font-semibold text-white no-underline hover:bg-[#2A2A28]">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo cliente
        </a>
    </div>

</div>
