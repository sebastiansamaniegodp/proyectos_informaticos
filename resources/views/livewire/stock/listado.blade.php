@php
    use App\Models\Producto;
    use App\Models\Stock;

    // En pantalla grande cada fila pasa a columnas: producto, código, cerradas, abiertos y disponible.
    $columnas = 'lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(140px,190px)_110px_130px_minmax(110px,140px)] lg:items-center lg:gap-x-3 lg:px-5';
@endphp

<div class="flex max-w-[1200px] flex-1 flex-col px-4 pt-4 pb-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    <x-encabezado titulo="Stock" :migas="[['Inicio', route('inicio')], ['Stock']]" />

    <label for="busqueda" class="sr-only">Buscar en el stock</label>
    <input id="busqueda" type="search" wire:model.live.debounce.300ms="busqueda"
        placeholder="Buscar por nombre o código" autocomplete="off"
        class="min-h-11 w-full rounded-[10px] border border-[#D5D5CF] bg-white px-3.5 text-[15px] text-[#141413] outline-none focus:border-[#141413] sm:w-[300px]">

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

    <div class="mt-4 flex flex-col gap-4">
        @forelse ($this->grupos as $grupo)
            <section wire:key="familia-{{ $grupo['familia']->id_familia }}"
                class="overflow-hidden rounded-[10px] border border-[#E3E3DE] bg-white">
                <h2 class="m-0 px-4 py-3 font-titulo text-base font-semibold lg:px-5 lg:py-4 lg:text-[17px]">
                    {{ $grupo['familia']->nombre }}
                </h2>

                <div class="hidden border-t border-[#EDEDE8] py-2.5 text-xs font-medium tracking-[0.04em] text-[#5A5A54] uppercase {{ $columnas }}" aria-hidden="true">
                    <span>Producto</span>
                    <span>Código</span>
                    <span class="text-right">Cerradas</span>
                    <span class="text-right">Rollos abiertos</span>
                    <span class="text-right">Disponible</span>
                </div>

                <ul class="m-0 list-none p-0">
                    @foreach ($grupo['productos'] as $producto)
                        @php
                            $cerradas = Producto::cantidadConUnidad($producto->piezas_cerradas, $producto->unidad_venta);
                            $abiertos = $producto->rollos_abiertos.' '.($producto->rollos_abiertos == 1 ? 'abierto' : 'abiertos')
                                .' ('.Stock::formatearCantidad($producto->metros_sueltos).' m)';
                            $disponible = $producto->seCorta()
                                ? Stock::formatearCantidad($producto->disponible_total).' m'
                                : $cerradas;
                            $sinStock = $producto->disponible_total == 0;
                        @endphp
                        <li wire:key="producto-{{ $producto->id_producto }}">
                            <a href="{{ route('stock.detalle', $producto) }}" wire:navigate
                                class="flex items-start justify-between gap-3 border-t border-[#EDEDE8] px-4 py-3 text-inherit no-underline hover:bg-[#FAFAF8] {{ $columnas }}">
                                <span class="min-w-0 lg:contents">
                                    <span class="block text-[15px] font-medium">{{ $producto->medidas() ?: $producto->descripcion }}</span>
                                    <span class="mt-[3px] block text-xs text-[#5A5A54] lg:mt-0 lg:text-sm">{{ $producto->codigo }}</span>
                                    {{-- En el celular el detalle de cerradas y abiertos va abajo del código. --}}
                                    @if ($producto->seCorta() && ! $sinStock)
                                        <span class="mt-0.5 block text-xs text-[#5A5A54] lg:hidden">{{ $producto->piezas_cerradas }} {{ $producto->piezas_cerradas == 1 ? 'cerrado' : 'cerrados' }} · {{ $abiertos }}</span>
                                    @endif
                                    <span class="hidden text-right text-sm lg:block">{{ $producto->piezas_cerradas }}</span>
                                    <span class="hidden text-right text-sm lg:block">
                                        @if ($producto->seCorta())
                                            {{ $producto->rollos_abiertos }}
                                            @if ($producto->rollos_abiertos > 0)
                                                <span class="text-[#5A5A54]">· {{ Stock::formatearCantidad($producto->metros_sueltos) }} m</span>
                                            @endif
                                        @else
                                            <span class="text-[#A8A8A2]">—</span>
                                        @endif
                                    </span>
                                </span>
                                <span @class([
                                    'flex-none text-right text-[15px] font-semibold',
                                    'font-medium text-[#5A5A54]' => $sinStock,
                                ])>{{ $sinStock ? 'Sin stock' : $disponible }}</span>
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

        <p class="m-0 text-[13px] leading-normal text-[#5A5A54]">
            En los tejidos que se cortan, el disponible son los metros de los rollos cerrados más los que quedan en los abiertos.
        </p>
    </div>

</div>
