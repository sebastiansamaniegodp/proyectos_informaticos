@php
    use App\Models\Producto;
    use App\Models\RolloAbierto;
    use App\Models\Stock;

    $tarjeta = 'rounded-[10px] border border-[#E3E3DE] bg-white';
    $cifra = 'rounded-[10px] border border-[#E3E3DE] bg-white px-4 py-3';
    $resumen = $this->resumen;
    $unidad = $producto->unidad_venta;
    $venta = match (true) {
        $producto->seCorta() => 'se vende por rollo o por metro',
        $unidad === 'ROLLO' => 'se vende por rollo, no se corta',
        default => 'se vende por '.mb_strtolower(Producto::UNIDADES[$unidad]),
    };
    $botonNegro = 'inline-flex min-h-11 items-center justify-center rounded-[10px] bg-[#141413] px-5 text-[15px] font-semibold text-white no-underline hover:bg-[#2A2A28]';
    $botonBlanco = 'inline-flex min-h-11 items-center justify-center rounded-[10px] border border-[#D5D5CF] bg-white px-5 text-[15px] font-semibold text-[#141413] no-underline hover:bg-[#FAFAF8]';
@endphp

<div class="flex max-w-[1200px] flex-1 flex-col px-4 pt-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    <x-encabezado titulo="Stock del producto"
        :migas="[['Inicio', route('inicio')], ['Stock', route('stock.listado')], [$producto->codigo]]" />

    @if (session('aviso'))
        <div role="status" class="mb-3.5 rounded-[10px] border border-[#2F6B45]/30 bg-[#2F6B45]/8 px-4 py-3 text-sm font-medium text-[#2F6B45]">
            {{ session('aviso') }}
        </div>
    @endif

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <div class="text-[17px] font-semibold">{{ $producto->medidas() ?: $producto->descripcion }}</div>
            <div class="mt-0.5 text-sm text-[#5A5A54]">
                {{ $producto->codigo }} · {{ $producto->familia->nombre }}
                · {{ $venta }}
            </div>
        </div>
        <div class="hidden flex-none gap-2.5 sm:flex">
            <a href="{{ route('stock.ajuste', $producto) }}" wire:navigate class="{{ $botonBlanco }}">Ajuste</a>
            <a href="{{ route('stock.ingreso', $producto) }}" wire:navigate class="{{ $botonNegro }}">Ingreso</a>
        </div>
    </div>

    <div @class(['mt-4 grid gap-2.5', 'grid-cols-3' => $producto->seCorta(), 'grid-cols-1 sm:max-w-[260px]' => ! $producto->seCorta()])>
        @if ($producto->seCorta())
            <div class="{{ $cifra }}">
                <div class="text-xs text-[#5A5A54]">Disponible</div>
                <div class="mt-0.5 text-xl font-semibold">{{ Stock::formatearCantidad($resumen->disponible_total) }} m</div>
            </div>
            <div class="{{ $cifra }}">
                <div class="text-xs text-[#5A5A54]">Rollos cerrados</div>
                <div class="mt-0.5 text-xl font-semibold">{{ $resumen->piezas_cerradas }}</div>
            </div>
            <div class="{{ $cifra }}">
                <div class="text-xs text-[#5A5A54]">Abiertos</div>
                <div class="mt-0.5 text-xl font-semibold">{{ $resumen->rollos_abiertos }}</div>
            </div>
        @else
            <div class="{{ $cifra }}">
                <div class="text-xs text-[#5A5A54]">Disponible</div>
                <div class="mt-0.5 text-xl font-semibold">{{ Producto::cantidadConUnidad($resumen->piezas_cerradas, $unidad) }}</div>
            </div>
        @endif
    </div>

    @if ($producto->seCorta())
        <section class="mt-4 overflow-hidden {{ $tarjeta }}">
            <h2 class="m-0 px-4 py-3 font-titulo text-base font-semibold lg:px-5">Rollos abiertos</h2>
            @forelse ($this->rollos as $rollo)
                @php $porcentaje = $rollo->metros_iniciales > 0 ? round($rollo->metros_restantes / $rollo->metros_iniciales * 100) : 0; @endphp
                <div wire:key="rollo-{{ $rollo->id_rollo }}" class="border-t border-[#EDEDE8] px-4 py-3 lg:px-5">
                    <div class="flex items-baseline justify-between gap-3">
                        <div class="min-w-0">
                            <span class="text-[15px] font-medium">Rollo #{{ $rollo->id_rollo }}</span>
                            @if ($rollo->esRetazo())
                                <span class="ml-1.5 rounded-full border border-[#D5D5CF] px-2 py-px text-[11px] font-semibold text-[#44443F]">Retazo · {{ RolloAbierto::DESCUENTO_RETAZO }}% menos</span>
                            @endif
                            <div class="mt-0.5 text-xs text-[#5A5A54]">Abierto el {{ $rollo->fecha_apertura->format('d/m/Y') }}</div>
                        </div>
                        <div class="flex-none text-right text-[15px]">
                            <span class="font-semibold">{{ Stock::formatearCantidad($rollo->metros_restantes) }} m</span>
                            <span class="text-[#5A5A54]">de {{ Stock::formatearCantidad($rollo->metros_iniciales) }}</span>
                        </div>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-[#EDEDE8]" aria-hidden="true">
                        <div class="h-full rounded-full bg-[#141413]" style="width: {{ $porcentaje }}%"></div>
                    </div>
                </div>
            @empty
                <div class="border-t border-[#EDEDE8] px-4 py-4 text-sm text-[#5A5A54] lg:px-5">
                    No hay rollos abiertos. El próximo corte abre un rollo cerrado.
                </div>
            @endforelse
        </section>
    @endif

    <section class="mt-4 mb-4 overflow-hidden sm:mb-0 {{ $tarjeta }}">
        <h2 class="m-0 px-4 py-3 font-titulo text-base font-semibold lg:px-5">Últimos movimientos</h2>
        <ul class="m-0 list-none p-0">
            @forelse ($this->movimientos as $movimiento)
                @php
                    $detalle = array_filter([
                        $movimiento->id_rollo ? 'Rollo #'.$movimiento->id_rollo : null,
                        $movimiento->id_pedido ? 'Pedido #'.$movimiento->id_pedido : null,
                        $movimiento->observacion,
                    ]);
                @endphp
                <li wire:key="movimiento-{{ $movimiento->id_movimiento }}" class="flex items-start justify-between gap-3 border-t border-[#EDEDE8] px-4 py-3 lg:px-5">
                    <div class="min-w-0">
                        <div class="text-[15px] font-medium">{{ $movimiento->etiqueta() }}</div>
                        <div class="mt-0.5 text-xs leading-normal text-[#5A5A54]">
                            {{ $movimiento->fecha->format('d/m/Y H:i') }}@if ($detalle) · {{ implode(' · ', $detalle) }}@endif
                        </div>
                    </div>
                    <span @class([
                        'flex-none text-[15px] font-semibold',
                        'text-[#2F6B45]' => $movimiento->cantidad > 0,
                        'text-[#B3261E]' => $movimiento->cantidad < 0,
                    ])>{{ $movimiento->cantidadConSigno() }}</span>
                </li>
            @empty
                <li class="border-t border-[#EDEDE8] px-4 py-4 text-sm text-[#5A5A54] lg:px-5">
                    Este producto todavía no tiene movimientos. La mercadería entra con un ingreso.
                </li>
            @endforelse
        </ul>
    </section>

    {{-- En el celular los botones quedan abajo, a mano del pulgar. --}}
    <div class="sticky bottom-0 -mx-4 mt-auto flex gap-2.5 border-t border-[#E3E3DE] bg-white px-4 pt-3 pb-4 sm:hidden">
        <a href="{{ route('stock.ajuste', $producto) }}" wire:navigate class="{{ $botonBlanco }} min-h-[52px] flex-1 text-base">Ajuste</a>
        <a href="{{ route('stock.ingreso', $producto) }}" wire:navigate class="{{ $botonNegro }} min-h-[52px] flex-1 text-base">Ingreso</a>
    </div>

</div>
