@php
    // En pantalla grande cada fila pasa a columnas: foto, producto, código y precio.
    $columnas = 'lg:grid lg:grid-cols-[38px_minmax(0,1fr)_minmax(140px,190px)_minmax(130px,160px)] lg:items-center lg:gap-x-3 lg:px-5';
@endphp

<div class="flex max-w-[1200px] flex-1 flex-col px-4 pt-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    <x-encabezado titulo="Catálogo" :migas="[['Inicio', route('inicio')], ['Catálogo']]" />

    <div class="flex flex-wrap items-center justify-between gap-2.5">
        <label for="busqueda" class="sr-only">Buscar en el catálogo</label>
        <input id="busqueda" type="search" wire:model.live.debounce.300ms="busqueda"
            placeholder="Buscar por nombre o código" autocomplete="off"
            class="min-h-11 w-full rounded-[10px] border border-[#D5D5CF] bg-white px-3.5 text-[15px] text-[#141413] outline-none focus:border-[#141413] sm:w-[300px]">

        <a href="{{ route('productos.nuevo') }}" wire:navigate
            class="hidden min-h-11 items-center gap-2 rounded-[10px] bg-[#141413] px-4 text-[15px] font-semibold text-white no-underline hover:bg-[#2A2A28] sm:inline-flex">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo producto
        </a>
    </div>

    <div class="-mx-4 mt-3 flex gap-2 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0" role="group" aria-label="Filtrar por familia">
        @foreach ($this->familias as $item)
            @php $elegida = $familia === $item->id_familia; @endphp
            <button type="button" wire:click="filtrar({{ $item->id_familia }})" aria-pressed="{{ $elegida ? 'true' : 'false' }}"
                @class([
                    'min-h-[34px] flex-none cursor-pointer rounded-full px-3 text-[13px]',
                    'border-0 bg-[#141413] font-semibold text-white' => $elegida,
                    'border border-[#D5D5CF] bg-white font-medium text-[#44443F] hover:border-[#A8A8A2]' => ! $elegida,
                ])>
                {{ $item->nombreCorto() }}
            </button>
        @endforeach
    </div>

    <div class="mt-4 mb-4 flex flex-col gap-4 sm:mb-0">
        @forelse ($this->grupos as $grupo)
            @php $cantidad = $grupo['productos']->count(); @endphp
            <section wire:key="familia-{{ $grupo['familia']->id_familia }}"
                class="overflow-hidden rounded-[10px] border border-[#E3E3DE] bg-white">
                <h2 class="m-0 px-4 py-3 font-titulo text-base font-semibold lg:px-5 lg:py-4 lg:text-[17px]">
                    {{ $grupo['familia']->nombre }}
                    <span class="font-sans text-sm font-normal text-[#5A5A54]">· {{ $cantidad }} {{ $cantidad === 1 ? 'producto' : 'productos' }}</span>
                </h2>

                <div class="hidden border-t border-[#EDEDE8] py-2.5 text-xs font-medium tracking-[0.04em] text-[#5A5A54] uppercase {{ $columnas }}" aria-hidden="true">
                    <span class="col-span-2">Producto</span>
                    <span>Código</span>
                    <span class="text-right">Precio</span>
                </div>

                <ul class="m-0 list-none p-0">
                    @foreach ($grupo['productos'] as $producto)
                        <li wire:key="producto-{{ $producto->id_producto }}">
                            <a href="{{ route('productos.editar', $producto) }}" wire:navigate
                                class="flex gap-3 border-t border-[#EDEDE8] px-4 py-3 text-inherit no-underline hover:bg-[#FAFAF8] {{ $columnas }}">
                                @if ($producto->fotoUrl())
                                    <img src="{{ $producto->fotoUrl() }}" alt="" loading="lazy"
                                        class="size-14 flex-none rounded-lg object-cover lg:size-[38px] lg:rounded-md">
                                @else
                                    <span class="flex size-14 flex-none items-center justify-center rounded-lg bg-[#EDEDE8] text-[#A8A8A2] lg:size-[38px] lg:rounded-md" aria-hidden="true">
                                        <svg class="size-6 lg:size-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4l16 16M20 4L4 20M12 4l8 8-8 8-8-8z"/></svg>
                                    </span>
                                @endif

                                <span class="flex min-w-0 flex-1 items-start justify-between gap-2.5 lg:contents">
                                    <span class="min-w-0 lg:contents">
                                        <span class="block text-[15px] font-medium">{{ $producto->medidas() ?: $producto->descripcion }}</span>
                                        <span class="mt-[3px] block text-xs text-[#5A5A54] lg:mt-0 lg:text-sm">
                                            {{ $producto->codigo }}@if ($producto->unidad_venta === 'ROLLO' && ! $producto->seCorta()) · no se corta @endif
                                        </span>
                                    </span>
                                    <span class="flex-none text-right">
                                        <span class="block text-[15px] font-semibold">{{ App\Models\Producto::formatearPrecio($producto->precio_venta) }}</span>
                                        @if ($producto->seCorta())
                                            <span class="mt-0.5 block text-xs text-[#5A5A54]">{{ App\Models\Producto::formatearPrecio($producto->precio_metro) }} el metro</span>
                                        @elseif ($producto->unidad_venta !== 'ROLLO')
                                            <span class="mt-0.5 block text-xs text-[#5A5A54]">{{ ['METRO' => 'el metro', 'KILO' => 'el kilo', 'UNIDAD' => 'la unidad'][$producto->unidad_venta] }}</span>
                                        @endif
                                    </span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @empty
            <div class="rounded-[10px] border border-[#E3E3DE] bg-white px-4 py-8 text-center text-sm leading-normal text-[#5A5A54]">
                @if (trim($busqueda) !== '' || $familia)
                    No hay productos que coincidan con la búsqueda.
                @else
                    Todavía no hay productos cargados.
                @endif
            </div>
        @endforelse
    </div>

    {{-- En el celular el botón queda abajo, a mano del pulgar, como en el prototipo. --}}
    <div class="sticky bottom-0 -mx-4 mt-auto border-t border-[#E3E3DE] bg-white px-4 pt-3 pb-4 sm:hidden">
        <a href="{{ route('productos.nuevo') }}" wire:navigate
            class="flex min-h-[52px] items-center justify-center gap-2 rounded-[10px] bg-[#141413] text-base font-semibold text-white no-underline hover:bg-[#2A2A28]">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo producto
        </a>
    </div>

</div>
