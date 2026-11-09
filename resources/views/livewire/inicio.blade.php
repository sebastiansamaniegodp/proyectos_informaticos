@php
    use App\Models\PedidoDetalle;
    use App\Models\Producto;
    use App\Models\RolloAbierto;
    use App\Models\Stock;
    use App\Support\Guaranies;

    $tarjeta = 'rounded-[10px] border border-[#E3E3DE] bg-white';
    $titulo = 'm-0 font-titulo text-base font-semibold';
    $enlace = 'inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-[#141413] no-underline underline-offset-2 hover:underline';
    $flecha = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';

    $hoy = now()->locale('es');
    $nombreMes = $hoy->translatedFormat('F');
    $mes = $this->mes;
    $comparacion = $this->comparacion;
    $abiertos = $this->abiertos;
    $pendientes = $abiertos['BORRADOR']->cantidad + $abiertos['CONFIRMADO']->cantidad;
    $hayVentas = $this->meses->sum('ventas') > 0;
@endphp

<div class="flex max-w-[1200px] flex-1 flex-col px-4 pt-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    <div class="mb-4 flex items-end justify-between gap-4 lg:mb-5">
        <div>
            <h1 class="m-0 font-titulo text-[22px] font-bold lg:text-[26px]">Inicio</h1>
            <p class="m-0 mt-0.5 text-sm text-[#5A5A54]">Hola, {{ auth()->user()->nombre }} · {{ ucfirst($hoy->translatedFormat('l j \d\e F')) }}</p>
        </div>
        <a href="{{ route('pedidos.nuevo') }}" wire:navigate
            class="hidden min-h-11 flex-none items-center gap-2 rounded-[10px] bg-[#141413] px-4 text-[15px] font-semibold text-white no-underline hover:bg-[#2A2A28] sm:inline-flex">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo pedido
        </a>
    </div>

    <div class="mb-4 flex flex-col gap-4 sm:mb-0">

        {{-- Cifras del mes y de los pedidos abiertos. --}}
        <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
            <a href="{{ route('reportes.resultado') }}" wire:navigate class="{{ $tarjeta }} block px-4 py-3 text-inherit no-underline hover:border-[#C9C9C3]">
                <div class="text-[13px] text-[#5A5A54]">Ventas de {{ $nombreMes }}</div>
                <div class="mt-0.5 text-[22px] font-semibold lg:text-[26px]">{{ Guaranies::completo($mes->ventas) }}</div>
                @if ($comparacion)
                    @php $sube = $comparacion->cambio >= 0; @endphp
                    <div class="text-xs leading-snug">
                        <span @class(['font-semibold', 'text-[#006300]' => $sube, 'text-[#B3261E]' => ! $sube])>
                            {{ $sube ? '▲' : '▼' }} {{ Guaranies::porcentaje(abs($comparacion->cambio)) }}
                        </span>
                        <span class="text-[#5A5A54]">vs. {{ $comparacion->tramo }}</span>
                    </div>
                @else
                    <div class="text-xs text-[#5A5A54]">{{ $mes->pedidos->count() }} {{ $mes->pedidos->count() == 1 ? 'pedido cobrado' : 'pedidos cobrados' }}</div>
                @endif
            </a>

            <a href="{{ route('reportes.resultado') }}" wire:navigate class="{{ $tarjeta }} block px-4 py-3 text-inherit no-underline hover:border-[#C9C9C3]">
                <div class="text-[13px] text-[#5A5A54]">Ganancia de {{ $nombreMes }}</div>
                <div class="mt-0.5 text-[22px] font-semibold lg:text-[26px]">{{ Guaranies::completo($mes->ganancia) }}</div>
                <div class="text-xs text-[#5A5A54]">
                    @if ($mes->ventas > 0) margen {{ Guaranies::porcentaje($mes->margen) }} · costo {{ Guaranies::compacto($mes->costo) }} @else sin cobros todavía @endif
                </div>
            </a>

            <a href="{{ route('pedidos.listado', ['filtro' => 'entregados']) }}" wire:navigate class="{{ $tarjeta }} block px-4 py-3 text-inherit no-underline hover:border-[#C9C9C3]">
                <div class="text-[13px] text-[#5A5A54]">Por cobrar</div>
                <div class="mt-0.5 text-[22px] font-semibold lg:text-[26px]">{{ Guaranies::completo($abiertos['ENTREGADO']->total) }}</div>
                <div class="text-xs text-[#5A5A54]">
                    {{ $abiertos['ENTREGADO']->cantidad }} {{ $abiertos['ENTREGADO']->cantidad == 1 ? 'pedido entregado' : 'pedidos entregados' }}
                </div>
            </a>

            <a href="{{ route('pedidos.listado') }}" wire:navigate class="{{ $tarjeta }} block px-4 py-3 text-inherit no-underline hover:border-[#C9C9C3]">
                <div class="text-[13px] text-[#5A5A54]">Pedidos pendientes</div>
                <div class="mt-0.5 text-[22px] font-semibold lg:text-[26px]">{{ $pendientes }}</div>
                <div class="text-xs text-[#5A5A54]">
                    {{ $abiertos['BORRADOR']->cantidad }} por confirmar · {{ $abiertos['CONFIRMADO']->cantidad }} por entregar
                </div>
            </a>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            {{-- El reporte que la dueña pidió ver cada mañana (REP-07). --}}
            <section class="{{ $tarjeta }} flex flex-col overflow-hidden">
                <div class="flex items-baseline justify-between gap-3 px-4 pt-3.5 pb-2 lg:px-5">
                    <h2 class="{{ $titulo }}">Pedidos por confirmar y entregar</h2>
                    <span class="text-[13px] text-[#5A5A54]">{{ $pendientes }}</span>
                </div>
                <ul class="m-0 flex-1 list-none p-0">
                    @forelse ($this->pendientes as $pedido)
                        <li wire:key="pendiente-{{ $pedido->id_pedido }}">
                            <a href="{{ $pedido->esBorrador() ? route('pedidos.editar', $pedido) : route('pedidos.detalle', $pedido) }}" wire:navigate
                                class="flex items-start justify-between gap-3 border-t border-[#EDEDE8] px-4 py-3 text-inherit no-underline hover:bg-[#FAFAF8] lg:px-5">
                                <span class="min-w-0">
                                    <span class="flex items-center gap-2">
                                        <span class="truncate text-[15px] font-medium">{{ $pedido->cliente->nombre }}</span>
                                        <x-pedido.estado :estado="$pedido->estado" />
                                    </span>
                                    <span class="mt-0.5 block truncate text-[13px] text-[#5A5A54]">{{ $pedido->resumen() }}</span>
                                </span>
                                <span class="flex-none text-right">
                                    <span class="block text-[15px] font-semibold">{{ Producto::formatearPrecio($pedido->total) }}</span>
                                    <span class="block text-xs text-[#8A8A84]">{{ $pedido->fecha_pedido->format('d/m') }}</span>
                                </span>
                            </a>
                        </li>
                    @empty
                        <li class="border-t border-[#EDEDE8] px-4 py-6 text-center text-sm leading-normal text-[#5A5A54] lg:px-5">
                            No hay pedidos pendientes.
                            <a href="{{ route('pedidos.nuevo') }}" wire:navigate class="font-semibold text-[#141413] underline-offset-2 hover:underline">Cargar un pedido</a>
                        </li>
                    @endforelse
                </ul>
                <div class="border-t border-[#EDEDE8] px-4 lg:px-5">
                    <a href="{{ route('pedidos.listado') }}" wire:navigate class="{{ $enlace }}">Ver todos los pedidos {!! $flecha !!}</a>
                </div>
            </section>

            <section class="{{ $tarjeta }} flex flex-col overflow-hidden">
                <div class="flex items-baseline justify-between gap-3 px-4 pt-3.5 pb-2 lg:px-5">
                    <h2 class="{{ $titulo }}">Entregados sin cobrar</h2>
                    <span class="text-[13px] text-[#5A5A54]">{{ Guaranies::completo($abiertos['ENTREGADO']->total) }} Gs</span>
                </div>
                <ul class="m-0 flex-1 list-none p-0">
                    @forelse ($this->porCobrar as $pedido)
                        @php $dias = (int) $pedido->fecha_entrega->startOfDay()->diffInDays(today()); @endphp
                        <li wire:key="cobrar-{{ $pedido->id_pedido }}">
                            <a href="{{ route('pedidos.detalle', $pedido) }}" wire:navigate
                                class="flex items-start justify-between gap-3 border-t border-[#EDEDE8] px-4 py-3 text-inherit no-underline hover:bg-[#FAFAF8] lg:px-5">
                                <span class="min-w-0">
                                    <span class="block truncate text-[15px] font-medium">{{ $pedido->cliente->nombre }}</span>
                                    <span class="mt-0.5 block text-[13px] text-[#5A5A54]">
                                        Entregado {{ match (true) { $dias === 0 => 'hoy', $dias === 1 => 'ayer', default => 'hace '.$dias.' días' } }}
                                        @if ($pedido->cliente->telefono) · {{ $pedido->cliente->telefono }} @endif
                                    </span>
                                </span>
                                <span class="flex-none text-[15px] font-semibold">{{ Producto::formatearPrecio($pedido->total) }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="border-t border-[#EDEDE8] px-4 py-6 text-center text-sm text-[#5A5A54] lg:px-5">No hay pedidos entregados sin cobrar.</li>
                    @endforelse
                </ul>
                <div class="border-t border-[#EDEDE8] px-4 lg:px-5">
                    <a href="{{ route('pedidos.listado', ['filtro' => 'entregados']) }}" wire:navigate class="{{ $enlace }}">Ver los entregados {!! $flecha !!}</a>
                </div>
            </section>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <section class="{{ $tarjeta }} px-4 py-3.5 lg:px-5">
                <div class="mb-3">
                    <h2 class="{{ $titulo }}">Ventas de los últimos {{ \App\Livewire\Inicio::MESES }} meses</h2>
                    <p class="m-0 mt-0.5 text-[13px] text-[#5A5A54]">Pedidos cobrados, por mes de cobro. Sin flete.</p>
                </div>
                @if ($hayVentas)
                    <x-grafico.columnas :meses="$this->meses" titulo="Ventas de los últimos meses" />
                @else
                    <div class="rounded-[10px] bg-[#F4F4F1] px-4 py-8 text-center text-sm leading-normal text-[#5A5A54]">
                        Todavía no hay pedidos cobrados. Cuando registres el cobro de un pedido, acá vas a ver cuánto vendiste y cuánto ganaste cada mes.
                    </div>
                @endif
                <div class="mt-1 border-t border-[#EDEDE8]">
                    <a href="{{ route('reportes.resultado') }}" wire:navigate class="{{ $enlace }}">Ver el resultado del mes {!! $flecha !!}</a>
                </div>
            </section>

            <section class="{{ $tarjeta }} px-4 py-3.5 lg:px-5">
                <div class="mb-3">
                    <h2 class="{{ $titulo }}">Ventas por familia</h2>
                    <p class="m-0 mt-0.5 text-[13px] text-[#5A5A54]">Últimos {{ \App\Livewire\Inicio::MESES }} meses.</p>
                </div>
                @if ($this->familias->isNotEmpty())
                    <x-grafico.barras :filas="$this->familias" titulo="Ventas por familia de los últimos meses" />
                @else
                    <div class="rounded-[10px] bg-[#F4F4F1] px-4 py-8 text-center text-sm text-[#5A5A54]">Sin ventas cobradas en estos meses.</div>
                @endif
            </section>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="{{ $tarjeta }} flex flex-col overflow-hidden">
                <div class="px-4 pt-3.5 pb-2 lg:px-5">
                    <h2 class="{{ $titulo }}">Rollos abiertos</h2>
                    <p class="m-0 mt-0.5 text-[13px] text-[#5A5A54]">Primero los que tienen menos metros: son los próximos en cortarse.</p>
                </div>
                <ul class="m-0 flex-1 list-none p-0">
                    @forelse ($this->rollos as $rollo)
                        @php $porcentaje = $rollo->metros_iniciales > 0 ? round($rollo->metros_restantes / $rollo->metros_iniciales * 100) : 0; @endphp
                        <li wire:key="rollo-{{ $rollo->id_rollo }}">
                            <a href="{{ route('stock.detalle', $rollo->id_producto) }}" wire:navigate
                                class="block border-t border-[#EDEDE8] px-4 py-3 text-inherit no-underline hover:bg-[#FAFAF8] lg:px-5">
                                <span class="flex items-baseline justify-between gap-3">
                                    <span class="min-w-0">
                                        <span class="block truncate text-[15px] font-medium">{{ $rollo->producto->nombreCompleto() }}</span>
                                        <span class="mt-0.5 flex items-center gap-1.5 text-xs text-[#5A5A54]">
                                            Rollo #{{ $rollo->id_rollo }}
                                            @if ($rollo->esRetazo())
                                                <span class="rounded-full border border-[#D5D5CF] px-2 py-px text-[11px] font-semibold text-[#44443F]">Retazo · {{ RolloAbierto::DESCUENTO_RETAZO }}% menos</span>
                                            @endif
                                        </span>
                                    </span>
                                    <span class="flex-none text-right text-[15px]">
                                        <span class="font-semibold">{{ Stock::formatearCantidad($rollo->metros_restantes) }} m</span>
                                        <span class="text-[#5A5A54]">de {{ Stock::formatearCantidad($rollo->metros_iniciales) }}</span>
                                    </span>
                                </span>
                                <span class="mt-2 block h-1.5 overflow-hidden rounded-full bg-[#EDEDE8]" aria-hidden="true">
                                    <span class="block h-full rounded-full bg-[#141413]" style="width: {{ $porcentaje }}%"></span>
                                </span>
                            </a>
                        </li>
                    @empty
                        <li class="border-t border-[#EDEDE8] px-4 py-6 text-center text-sm text-[#5A5A54] lg:px-5">No hay rollos abiertos. El próximo corte abre un rollo cerrado.</li>
                    @endforelse
                </ul>
                <div class="border-t border-[#EDEDE8] px-4 lg:px-5">
                    <a href="{{ route('stock.listado') }}" wire:navigate class="{{ $enlace }}">Ver el stock completo {!! $flecha !!}</a>
                </div>
            </section>

            <section class="{{ $tarjeta }} flex flex-col overflow-hidden">
                <div class="px-4 pt-3.5 pb-2 lg:px-5">
                    <h2 class="{{ $titulo }}">Lo más vendido</h2>
                    <p class="m-0 mt-0.5 text-[13px] text-[#5A5A54]">Por importe, en los últimos {{ \App\Livewire\Inicio::MESES }} meses.</p>
                </div>
                <ol class="m-0 flex-1 list-none p-0">
                    @forelse ($this->masVendidos as $vendido)
                        @php
                            $cantidades = array_filter([
                                $vendido->piezas > 0 ? Producto::cantidadConUnidad($vendido->piezas, $vendido->producto->unidad_venta) : null,
                                $vendido->metros > 0 ? Stock::formatearCantidad($vendido->metros).' m cortados' : null,
                            ]);
                        @endphp
                        <li wire:key="vendido-{{ $vendido->producto->id_producto }}" class="flex items-start gap-3 border-t border-[#EDEDE8] px-4 py-3 lg:px-5">
                            <span class="flex size-6 flex-none items-center justify-center rounded-full bg-[#F4F4F1] text-xs font-semibold text-[#44443F]">{{ $loop->iteration }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[15px] font-medium">{{ $vendido->producto->nombreCompleto() }}</span>
                                <span class="mt-0.5 block text-xs text-[#5A5A54]">{{ implode(' + ', $cantidades) }}</span>
                            </span>
                            <span class="flex-none text-[15px] font-semibold">{{ Guaranies::compacto($vendido->ventas) }}</span>
                        </li>
                    @empty
                        <li class="border-t border-[#EDEDE8] px-4 py-6 text-center text-sm text-[#5A5A54] lg:px-5">Sin ventas cobradas en estos meses.</li>
                    @endforelse
                </ol>
                <div class="border-t border-[#EDEDE8] px-4 lg:px-5">
                    <a href="{{ route('productos.catalogo') }}" wire:navigate class="{{ $enlace }}">Ver el catálogo {!! $flecha !!}</a>
                </div>
            </section>
        </div>
    </div>

    {{-- En el celular el botón queda abajo, a mano del pulgar. --}}
    <div class="sticky bottom-0 -mx-4 mt-auto border-t border-[#E3E3DE] bg-white px-4 pt-3 pb-4 sm:hidden">
        <a href="{{ route('pedidos.nuevo') }}" wire:navigate
            class="flex min-h-[52px] items-center justify-center gap-2 rounded-[10px] bg-[#141413] text-base font-semibold text-white no-underline hover:bg-[#2A2A28]">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo pedido
        </a>
    </div>

</div>
