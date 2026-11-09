@php
    use App\Livewire\Pedidos\Listado;
    use App\Models\Producto;

    // En pantalla grande cada fila pasa a columnas: cliente, detalle, estado y total.
    $columnas = 'lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_110px_minmax(110px,140px)] lg:items-center lg:gap-x-4 lg:px-5';
    $pedidos = $this->pedidos;
@endphp

<div class="flex max-w-[1200px] flex-1 flex-col px-4 pt-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    <x-encabezado titulo="Pedidos" :migas="[['Inicio', route('inicio')], ['Pedidos']]" />

    @if (session('aviso'))
        <div role="status" class="mb-3.5 rounded-[10px] border border-[#2F6B45]/30 bg-[#2F6B45]/8 px-4 py-3 text-sm font-medium text-[#2F6B45]">
            {{ session('aviso') }}
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2.5">
        <label for="busqueda" class="sr-only">Buscar por cliente</label>
        <input id="busqueda" type="search" wire:model.live.debounce.300ms="busqueda"
            placeholder="Buscar por cliente" autocomplete="off"
            class="min-h-11 w-full rounded-[10px] border border-[#D5D5CF] bg-white px-3.5 text-[15px] text-[#141413] outline-none focus:border-[#141413] sm:w-[300px]">

        <a href="{{ route('pedidos.nuevo') }}" wire:navigate
            class="hidden min-h-11 items-center gap-2 rounded-[10px] bg-[#141413] px-4 text-[15px] font-semibold text-white no-underline hover:bg-[#2A2A28] sm:inline-flex">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo pedido
        </a>
    </div>

    <div class="-mx-4 mt-3 flex gap-2 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0" role="group" aria-label="Filtrar por estado">
        @foreach (Listado::FILTROS as $clave => [$nombre])
            @php $elegido = $filtro === $clave; @endphp
            <button type="button" wire:click="filtrar('{{ $clave }}')" aria-pressed="{{ $elegido ? 'true' : 'false' }}"
                @class([
                    'inline-flex min-h-[34px] flex-none cursor-pointer items-center gap-1.5 rounded-full px-3 text-[13px]',
                    'border-0 bg-[#141413] font-semibold text-white' => $elegido,
                    'border border-[#D5D5CF] bg-white font-medium text-[#44443F] hover:border-[#A8A8A2]' => ! $elegido,
                ])>
                {{ $nombre }}
                <span @class(['text-xs', 'text-white/70' => $elegido, 'text-[#8A8A84]' => ! $elegido])>{{ $this->cantidades[$clave] }}</span>
            </button>
        @endforeach
    </div>

    <div class="mt-4 mb-4 flex flex-col gap-4 sm:mb-0">
        @if ($pedidos->isNotEmpty())
            <section class="overflow-hidden rounded-[10px] border border-[#E3E3DE] bg-white" wire:loading.class="opacity-60" wire:target="filtrar,busqueda,nextPage,previousPage">
                <div class="hidden py-2.5 text-xs font-medium tracking-[0.04em] text-[#5A5A54] uppercase {{ $columnas }}" aria-hidden="true">
                    <span>Cliente</span>
                    <span>Detalle</span>
                    <span>Estado</span>
                    <span class="text-right">Total</span>
                </div>

                <ul class="m-0 list-none p-0">
                    @foreach ($pedidos as $pedido)
                        @php
                            $lineas = $pedido->lineas->count();
                            $detalle = $pedido->resumen().($pedido->estado === 'ENTREGADO' ? ' · falta cobrar' : '');
                        @endphp
                        <li wire:key="pedido-{{ $pedido->id_pedido }}">
                            <a href="{{ $pedido->esBorrador() ? route('pedidos.editar', $pedido) : route('pedidos.detalle', $pedido) }}" wire:navigate
                                class="block border-t border-[#EDEDE8] px-4 py-3 text-inherit no-underline first:border-t-0 hover:bg-[#FAFAF8] lg:first:border-t {{ $columnas }}">
                                <span class="flex items-start justify-between gap-3 lg:block">
                                    <span class="min-w-0">
                                        <span class="block truncate text-[15px] font-medium">{{ $pedido->cliente->nombre }}</span>
                                        <span class="mt-0.5 block text-xs text-[#5A5A54]">
                                            N° {{ $pedido->numero() }} · {{ $pedido->fecha_pedido->format('d/m') }} · {{ $lineas }} {{ $lineas == 1 ? 'línea' : 'líneas' }}
                                        </span>
                                    </span>
                                    <x-pedido.estado :estado="$pedido->estado" class="mt-0.5 lg:hidden" />
                                </span>
                                {{-- En el celular el detalle y el total van en una línea abajo. --}}
                                <span class="mt-1.5 flex items-baseline justify-between gap-3 lg:contents">
                                    <span class="min-w-0 truncate text-[13px] text-[#44443F] lg:text-sm">{{ $detalle }}</span>
                                    <span class="hidden lg:block"><x-pedido.estado :estado="$pedido->estado" /></span>
                                    <span @class([
                                        'flex-none text-right text-[15px] font-semibold',
                                        'text-[#8A8A84] line-through' => $pedido->estado === 'ANULADO',
                                    ])>{{ Producto::formatearPrecio($pedido->total) }}</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>

            @if ($pedidos->hasPages())
                <nav class="flex items-center justify-between gap-2" aria-label="Páginas">
                    <button type="button" wire:click="previousPage" @disabled($pedidos->onFirstPage())
                        class="min-h-11 cursor-pointer rounded-[10px] border border-[#D5D5CF] bg-white px-4 text-sm font-semibold text-[#141413] hover:bg-[#FAFAF8] disabled:cursor-default disabled:opacity-40">
                        ← Más nuevos
                    </button>
                    <span class="text-[13px] text-[#5A5A54]">Página {{ $pedidos->currentPage() }}</span>
                    <button type="button" wire:click="nextPage" @disabled(! $pedidos->hasMorePages())
                        class="min-h-11 cursor-pointer rounded-[10px] border border-[#D5D5CF] bg-white px-4 text-sm font-semibold text-[#141413] hover:bg-[#FAFAF8] disabled:cursor-default disabled:opacity-40">
                        Más viejos →
                    </button>
                </nav>
            @endif
        @else
            <div class="rounded-[10px] border border-[#E3E3DE] bg-white px-4 py-8 text-center text-sm leading-normal text-[#5A5A54]">
                @if (trim($busqueda) !== '')
                    No hay pedidos de clientes que coincidan con la búsqueda.
                @elseif ($filtro === 'pendientes')
                    No hay pedidos pendientes. Los pedidos en borrador y los confirmados sin entregar aparecen acá.
                @else
                    No hay pedidos en esta lista.
                @endif
            </div>
        @endif

        @if ($filtro === 'pendientes')
            <p class="m-0 text-[13px] leading-normal text-[#5A5A54]">
                Un pedido en borrador no reserva mercadería: el stock recién se descuenta al confirmarlo.
            </p>
        @endif
    </div>

    {{-- En el celular el botón queda abajo, a mano del pulgar, como en el prototipo. --}}
    <div class="sticky bottom-0 -mx-4 mt-auto border-t border-[#E3E3DE] bg-white px-4 pt-3 pb-4 sm:hidden">
        <a href="{{ route('pedidos.nuevo') }}" wire:navigate
            class="flex min-h-[52px] items-center justify-center gap-2 rounded-[10px] bg-[#141413] text-base font-semibold text-white no-underline hover:bg-[#2A2A28]">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo pedido
        </a>
    </div>

</div>
