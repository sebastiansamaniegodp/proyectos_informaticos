@php
    use App\Support\Guaranies;

    $tarjeta = 'rounded-[10px] border border-[#E3E3DE] bg-white';
    $titulo = 'm-0 font-titulo text-base font-semibold';
    $resultado = $this->resultado;
    $fecha = $this->fecha();
    $esActual = $fecha->isSameMonth(now());
    $nombreMes = ucfirst($fecha->locale('es')->translatedFormat('F Y'));
    $flecha = 'flex size-11 cursor-pointer items-center justify-center rounded-[10px] border border-[#D5D5CF] bg-white text-[#141413] hover:bg-[#FAFAF8] disabled:cursor-default disabled:opacity-35';
    $parteCosto = $resultado->ventas > 0 ? min(100, max(0, $resultado->costo / $resultado->ventas * 100)) : 0;
@endphp

<div class="flex max-w-[1200px] flex-1 flex-col px-4 pt-4 pb-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    <x-encabezado titulo="Resultado del mes" :migas="[['Inicio', route('inicio')], ['Resultado del mes']]" />

    <div class="flex items-center gap-2" wire:loading.class="opacity-60" wire:target="anterior,siguiente">
        <button type="button" wire:click="anterior" class="{{ $flecha }}" aria-label="Mes anterior">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <h2 class="m-0 min-w-[150px] text-center font-titulo text-lg font-semibold">{{ $nombreMes }}</h2>
        <button type="button" wire:click="siguiente" @disabled($esActual) class="{{ $flecha }}" aria-label="Mes siguiente">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        </button>
        @if ($esActual)
            <span class="ml-1 text-[13px] text-[#5A5A54]">hasta hoy</span>
        @endif
    </div>

    <div class="mt-4 flex flex-col gap-4" wire:loading.class="opacity-60" wire:target="anterior,siguiente">

        <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
            <div class="{{ $tarjeta }} px-4 py-3">
                <div class="text-[13px] text-[#5A5A54]">Ventas</div>
                <div class="mt-0.5 text-[22px] font-semibold lg:text-[26px]">{{ Guaranies::completo($resultado->ventas) }}</div>
                <div class="text-xs text-[#5A5A54]">{{ $resultado->pedidos->count() }} {{ $resultado->pedidos->count() == 1 ? 'pedido cobrado' : 'pedidos cobrados' }}</div>
            </div>
            <div class="{{ $tarjeta }} px-4 py-3">
                <div class="text-[13px] text-[#5A5A54]">Costo</div>
                <div class="mt-0.5 text-[22px] font-semibold lg:text-[26px]">{{ Guaranies::completo($resultado->costo) }}</div>
                <div class="text-xs text-[#5A5A54]">de la mercadería vendida</div>
            </div>
            <div class="{{ $tarjeta }} px-4 py-3">
                <div class="text-[13px] text-[#5A5A54]">Ganancia</div>
                <div class="mt-0.5 text-[22px] font-semibold lg:text-[26px]">{{ Guaranies::completo($resultado->ganancia) }}</div>
                <div class="text-xs text-[#5A5A54]">margen {{ Guaranies::porcentaje($resultado->margen) }}</div>
            </div>
            <div class="{{ $tarjeta }} px-4 py-3">
                <div class="text-[13px] text-[#5A5A54]">Flete cobrado</div>
                <div class="mt-0.5 text-[22px] font-semibold lg:text-[26px]">{{ Guaranies::completo($resultado->flete) }}</div>
                <div class="text-xs text-[#5A5A54]">aparte, no suma en ventas</div>
            </div>
        </div>

        @if ($resultado->lineas_sin_costo > 0)
            <div role="status" class="rounded-[10px] border border-[#E8C46A] bg-[#FDF3D6] px-4 py-3 text-sm leading-normal text-[#6B4E00]">
                {{ $resultado->lineas_sin_costo }} {{ $resultado->lineas_sin_costo == 1 ? 'línea vendida no tenía' : 'líneas vendidas no tenían' }} costo cargado al confirmarse: cuentan con costo cero y la ganancia sale más alta de lo real.
            </div>
        @endif

        @if ($resultado->ventas > 0)
            <div class="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                {{-- De cada 100 Gs vendidos, cuánto es costo y cuánto queda. --}}
                <section class="{{ $tarjeta }} px-4 py-3.5 lg:px-5">
                    <h2 class="{{ $titulo }}">De lo vendido</h2>
                    <p class="m-0 mt-1 text-[13px] text-[#5A5A54]">De cada 100 Gs que entraron, cuánto fue costo y cuánto quedó.</p>
                    @php $parteGanancia = 100 - $parteCosto; @endphp
                    <div class="mt-4 flex h-7 gap-[2px]" role="img"
                        aria-label="Ganancia {{ Guaranies::porcentaje($parteGanancia) }}, costo {{ Guaranies::porcentaje($parteCosto) }} de las ventas">
                        @if ($parteGanancia > 0)
                            <div class="flex h-full items-center rounded-[4px] bg-[#2a78d6] px-2 text-xs font-semibold text-white" style="flex: {{ $parteGanancia }} 1 0">
                                @if ($parteGanancia >= 14) {{ Guaranies::porcentaje($parteGanancia) }} @endif
                            </div>
                        @endif
                        @if ($parteCosto > 0)
                            <div class="flex h-full items-center rounded-[4px] bg-[#eb6834] px-2 text-xs font-semibold text-white" style="flex: {{ $parteCosto }} 1 0">
                                @if ($parteCosto >= 14) {{ Guaranies::porcentaje($parteCosto) }} @endif
                            </div>
                        @endif
                    </div>
                    <div class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1 text-[13px] text-[#44443F]">
                        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-[#2a78d6]"></span>Ganancia {{ Guaranies::porcentaje($parteGanancia) }}</span>
                        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-[#eb6834]"></span>Costo {{ Guaranies::porcentaje($parteCosto) }}</span>
                    </div>
                </section>

                <section class="{{ $tarjeta }} px-4 py-3.5 lg:px-5">
                    <h2 class="{{ $titulo }} mb-3">Por familia</h2>
                    <x-grafico.barras :filas="$this->familias" titulo="Ventas de {{ $nombreMes }} por familia" />
                </section>
            </div>
        @endif

        <section class="{{ $tarjeta }} overflow-hidden">
            <h2 class="{{ $titulo }} px-4 pt-3.5 pb-2 lg:px-5">Pedidos cobrados</h2>
            @if ($resultado->pedidos->isNotEmpty())
                <div class="hidden border-t border-[#EDEDE8] px-5 py-2.5 text-xs font-medium tracking-[0.04em] text-[#5A5A54] uppercase lg:grid lg:grid-cols-[minmax(0,1fr)_120px_120px_120px] lg:gap-x-4" aria-hidden="true">
                    <span>Cliente</span>
                    <span class="text-right">Ventas</span>
                    <span class="text-right">Costo</span>
                    <span class="text-right">Ganancia</span>
                </div>
                <ul class="m-0 list-none p-0">
                    @foreach ($resultado->pedidos as $pedido)
                        <li wire:key="cobrado-{{ $pedido->id_pedido }}">
                            <a href="{{ route('pedidos.detalle', $pedido->id_pedido) }}" wire:navigate
                                class="flex items-start justify-between gap-3 border-t border-[#EDEDE8] px-4 py-3 text-inherit no-underline hover:bg-[#FAFAF8] lg:grid lg:grid-cols-[minmax(0,1fr)_120px_120px_120px] lg:items-center lg:gap-x-4 lg:px-5">
                                <span class="min-w-0">
                                    <span class="block truncate text-[15px] font-medium">{{ $pedido->cliente }}</span>
                                    <span class="mt-0.5 block text-xs text-[#5A5A54]">N° {{ $pedido->numero }} · cobrado el {{ $pedido->fecha_cobro->format('d/m') }}</span>
                                </span>
                                <span class="flex-none text-right text-[15px] font-semibold lg:font-medium tabular-nums">
                                    {{ Guaranies::completo($pedido->subtotal) }}
                                    <span class="block text-xs font-normal text-[#5A5A54] lg:hidden">ganancia {{ Guaranies::completo($pedido->ganancia) }}</span>
                                </span>
                                <span class="hidden text-right text-sm text-[#44443F] tabular-nums lg:block">{{ Guaranies::completo($pedido->costo) }}</span>
                                <span class="hidden text-right text-sm font-semibold tabular-nums lg:block">{{ Guaranies::completo($pedido->ganancia) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="border-t border-[#EDEDE8] px-4 py-6 text-center text-sm text-[#5A5A54] lg:px-5">
                    {{ $esActual ? 'Todavía no hay pedidos cobrados este mes.' : 'No hubo pedidos cobrados en '.mb_strtolower($nombreMes).'.' }}
                </div>
            @endif
        </section>

        <p class="m-0 text-[13px] leading-normal text-[#5A5A54]">
            Solo suman los pedidos cobrados, según su fecha de cobro. Los confirmados y los entregados sin cobrar todavía no aparecen. El costo es el que tenía cada producto al confirmarse el pedido.
        </p>

        <a href="{{ route('pedidos.listado') }}" wire:navigate
            class="{{ $tarjeta }} flex items-center justify-between gap-3 px-4 py-3.5 text-inherit no-underline hover:bg-[#FAFAF8] lg:px-5">
            <span>
                <span class="block text-[15px] font-semibold">Pedidos pendientes</span>
                <span class="mt-0.5 block text-[13px] text-[#5A5A54]">
                    {{ $this->pendientes === 0 ? 'No hay pedidos esperando.' : $this->pendientes.' '.($this->pendientes == 1 ? 'pedido esperando' : 'pedidos esperando').' confirmación o entrega' }}
                </span>
            </span>
            <svg class="flex-none text-[#8A8A84]" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        </a>
    </div>

</div>
