@php
    use App\Models\Producto;
    use App\Models\RolloAbierto;

    $tarjeta = 'rounded-[10px] border border-[#E3E3DE] bg-white';
    $titulo = 'm-0 font-titulo text-base font-semibold';
    $botonNegro = 'flex min-h-[52px] flex-1 cursor-pointer items-center justify-center gap-2 rounded-[10px] border-0 bg-[#141413] px-5 text-base font-semibold text-white no-underline hover:bg-[#2A2A28] disabled:cursor-default disabled:bg-[#5A5A54] sm:min-h-11 sm:flex-none sm:text-[15px]';
    $botonBlanco = 'flex min-h-[52px] flex-1 cursor-pointer items-center justify-center gap-2 rounded-[10px] border border-[#D5D5CF] bg-white px-5 text-base font-semibold text-[#141413] no-underline hover:bg-[#FAFAF8] disabled:cursor-default disabled:opacity-50 sm:min-h-11 sm:flex-none sm:text-[15px]';
    $botonAnular = 'min-h-11 cursor-pointer rounded-[10px] border-0 bg-transparent px-0 text-[15px] font-semibold text-[#B3261E] underline-offset-2 hover:underline';

    $cliente = $pedido->cliente;
    $estado = $pedido->estado;
    $fechas = array_filter([
        'Cargado' => $pedido->fecha_pedido,
        'Confirmado' => $pedido->fecha_confirmacion,
        'Entregado' => $pedido->fecha_entrega,
        'Cobrado' => $pedido->fecha_cobro,
    ]);
    $comprobante = $pedido->tieneComprobante()
        ? route('pedidos.comprobante', $pedido)
        : null;
    $archivo = app(\App\Services\ComprobanteService::class)->nombreArchivo($pedido);
@endphp

<div class="flex max-w-[760px] flex-1 flex-col px-4 pt-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    <x-encabezado :titulo="'Pedido N° '.$pedido->numero()" :migas="[['Inicio', route('inicio')], ['Pedidos', route('pedidos.listado')], ['N° '.$pedido->numero()]]" />

    <div class="mb-4 flex flex-col gap-3.5 sm:mb-0">

        @if ($aviso)
            <div role="status" wire:key="aviso-{{ md5($aviso) }}" class="rounded-[10px] border border-[#2F6B45]/30 bg-[#2F6B45]/8 px-4 py-3 text-sm font-medium text-[#2F6B45]">
                {{ $aviso }}
            </div>
        @endif

        @error('estado')
            <div role="alert" class="rounded-[10px] border border-[#B3261E]/30 bg-[#B3261E]/6 px-4 py-3 text-sm leading-normal font-medium text-[#B3261E]">{{ $message }}</div>
        @enderror

        <div class="flex flex-wrap items-center gap-2 text-[13px] text-[#5A5A54]">
            <x-pedido.estado :estado="$estado" />
            <span>
                @switch($estado)
                    @case('BORRADOR') No reserva mercadería hasta que lo confirmes. @break
                    @case('CONFIRMADO') Stock descontado. Falta entregarlo. @break
                    @case('ENTREGADO') Entregado. Falta registrar el cobro. @break
                    @case('COBRADO') Cobrado. Suma en el resultado de {{ $pedido->fecha_cobro->locale('es')->translatedFormat('F') }}. @break
                    @default Anulado. No suma en ningún reporte.
                @endswitch
            </span>
        </div>

        <section class="{{ $tarjeta }} px-4 py-3.5">
            <div class="text-[13px] font-medium text-[#44443F]">Cliente</div>
            <div class="mt-1 text-[15px] font-semibold">{{ $cliente->nombre }}</div>
            <div class="mt-0.5 text-[13px] text-[#5A5A54]">{{ $cliente->contacto() }}</div>
            <dl class="m-0 mt-3 grid grid-cols-2 gap-x-4 gap-y-1.5 border-t border-[#EDEDE8] pt-3 text-[13px] sm:grid-cols-4">
                @foreach ($fechas as $nombre => $fecha)
                    <div>
                        <dt class="text-[#5A5A54]">{{ $nombre }}</dt>
                        <dd class="m-0 font-medium">{{ $fecha->format('d/m/Y H:i') }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="{{ $tarjeta }} overflow-hidden">
            <h2 class="{{ $titulo }} px-4 pt-3.5 pb-2">Líneas</h2>
            <ul class="m-0 list-none p-0">
                @foreach ($pedido->lineas as $linea)
                    @php
                        $detalle = $linea->cantidadCorta();
                        if ($linea->esCorte() && $linea->id_rollo) $detalle .= ' del rollo #'.$linea->id_rollo;
                        if (! $linea->esCorte() && $linea->tipo_venta === 'ROLLO' && $linea->cantidad == 1) $detalle .= ' entero';
                        $detalle .= ' × '.Producto::formatearPrecio($linea->precio_unitario);
                        if ($linea->esCorte() && $linea->descuento_pct == RolloAbierto::DESCUENTO_RETAZO) $detalle .= ' · retazo '.RolloAbierto::DESCUENTO_RETAZO.'%';
                        elseif ($linea->descuento_pct > 0) $detalle .= ' · descuento '.str_replace('.', ',', (string) $linea->descuento_pct).'%';
                    @endphp
                    <li wire:key="linea-{{ $linea->nro_linea }}" class="flex items-start justify-between gap-3 border-t border-[#EDEDE8] px-4 py-3">
                        <div class="min-w-0">
                            <div class="text-[15px] font-medium">{{ $linea->producto->nombreCompleto() }}</div>
                            <div class="mt-0.5 text-[13px] text-[#5A5A54]">{{ $detalle }}</div>
                        </div>
                        <span class="flex-none text-[15px] font-semibold">{{ Producto::formatearPrecio($linea->subtotal) }}</span>
                    </li>
                @endforeach
            </ul>
            <dl class="m-0 border-t border-[#EDEDE8] px-4 py-3 text-[15px]">
                <div class="flex justify-between gap-3"><dt class="text-[#44443F]">Subtotal</dt><dd class="m-0">{{ Producto::formatearPrecio($pedido->subtotal) }}</dd></div>
                <div class="mt-1 flex justify-between gap-3"><dt class="text-[#44443F]">Flete</dt><dd class="m-0">{{ Producto::formatearPrecio($pedido->monto_flete) }}</dd></div>
                <div class="mt-2 flex justify-between gap-3 border-t border-[#EDEDE8] pt-2 text-lg font-semibold">
                    <dt>Total</dt>
                    <dd @class(['m-0', 'text-[#8A8A84] line-through' => $estado === 'ANULADO'])>{{ Producto::formatearPrecio($pedido->total) }}</dd>
                </div>
            </dl>
            @if ($pedido->observacion)
                <div class="border-t border-[#EDEDE8] px-4 py-3 text-[13px] leading-normal text-[#44443F]">
                    <span class="font-medium">Observación:</span> {{ $pedido->observacion }}
                </div>
            @endif
        </section>

        @if ($this->movimientos->isNotEmpty())
            <section class="{{ $tarjeta }} overflow-hidden">
                <h2 class="{{ $titulo }} px-4 pt-3.5 pb-2">Movimientos de stock</h2>
                <ul class="m-0 list-none p-0">
                    @foreach ($this->movimientos as $movimiento)
                        <li wire:key="movimiento-{{ $movimiento->id_movimiento }}" class="flex items-start justify-between gap-3 border-t border-[#EDEDE8] px-4 py-2.5">
                            <div class="min-w-0">
                                <a href="{{ route('stock.detalle', $movimiento->id_producto) }}" wire:navigate class="text-sm font-medium text-[#141413] underline-offset-2 hover:underline">{{ $movimiento->producto->nombreCorto() }}</a>
                                <div class="mt-0.5 text-xs text-[#5A5A54]">
                                    {{ $movimiento->etiqueta() }} · {{ $movimiento->fecha->format('d/m H:i') }}@if ($movimiento->id_rollo) · rollo #{{ $movimiento->id_rollo }}@endif
                                </div>
                            </div>
                            <span @class([
                                'flex-none text-sm font-semibold',
                                'text-[#2F6B45]' => $movimiento->cantidad > 0,
                                'text-[#B3261E]' => $movimiento->cantidad < 0,
                            ])>{{ $movimiento->cantidadConSigno() }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($comprobante)
            {{-- La clave cambia con el estado: así el PDF que se prepara es siempre el del estado actual. --}}
            <section wire:key="comprobante-{{ $estado }}" class="{{ $tarjeta }} px-4 py-3.5" x-data="compartirComprobante(@js($comprobante), @js($archivo), @js('Pedido N° '.$pedido->numero().' · Shopping de los Tejidos'))">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="{{ $titulo }}">Comprobante</h2>
                        <p class="m-0 mt-1 text-[13px] leading-normal text-[#5A5A54]">
                            Genera el PDF y abre el menú de compartir del celular para mandarlo por WhatsApp. No tiene validez fiscal.
                        </p>
                    </div>
                    <div class="flex flex-none gap-2.5">
                        <a href="{{ $comprobante }}?descargar=1" class="{{ $botonBlanco }}" download="{{ $archivo }}">Descargar</a>
                        <button type="button" x-on:click="compartir()" x-bind:disabled="enviando" class="{{ $botonNegro }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7M16 6l-4-4-4 4M12 2v13"/></svg>
                            <span x-text="enviando ? 'Generando…' : 'Enviar comprobante'">Enviar comprobante</span>
                        </button>
                    </div>
                </div>
                <p x-show="error" x-cloak x-text="error" class="m-0 mt-2 text-[13px] text-[#B3261E]"></p>
            </section>
        @endif

        @if (in_array($estado, ['CONFIRMADO', 'ENTREGADO'], true))
            <p class="m-0 text-[13px] leading-normal text-[#5A5A54]">
                @if ($estado === 'ENTREGADO')
                    Si anulás un pedido entregado, lo que salió vuelve al stock. Si la mercadería no vuelve, registrá un ajuste de stock.
                @else
                    Si anulás el pedido, las piezas vuelven a las cerradas y los metros al rollo del que se cortaron.
                @endif
            </p>
        @endif

        @if (! in_array($estado, ['COBRADO', 'ANULADO'], true))
            <div>
                <button type="button" wire:click="anular" wire:loading.attr="disabled"
                    wire:confirm="¿Anular el pedido N° {{ $pedido->numero() }}?{{ $estado === 'BORRADOR' ? '' : ' Lo que salió vuelve al stock.' }} No se puede deshacer."
                    class="{{ $botonAnular }}">
                    Anular pedido
                </button>
            </div>
        @endif
    </div>

    @if (! in_array($estado, ['COBRADO', 'ANULADO'], true))
        {{-- En el celular el paso siguiente queda fijo abajo, a mano del pulgar. --}}
        <div class="sticky bottom-0 -mx-4 mt-auto flex gap-2.5 border-t border-[#E3E3DE] bg-white px-4 pt-3 pb-4 sm:static sm:mx-0 sm:mt-4 sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 lg:mt-5">
            @switch($estado)
                @case('BORRADOR')
                    <a href="{{ route('pedidos.editar', $pedido) }}" wire:navigate class="{{ $botonBlanco }}">Editar</a>
                    <button type="button" wire:click="confirmar" wire:loading.attr="disabled" class="{{ $botonNegro }}">Confirmar</button>
                    @break
                @case('CONFIRMADO')
                    <button type="button" wire:click="marcarEntregado" wire:loading.attr="disabled" class="{{ $botonNegro }}">Marcar entregado</button>
                    @break
                @case('ENTREGADO')
                    <button type="button" wire:click="registrarCobro" wire:loading.attr="disabled" wire:confirm="¿Registrar el cobro de {{ Producto::formatearPrecio($pedido->total) }} Gs?" class="{{ $botonNegro }}">Registrar cobro</button>
                    @break
            @endswitch
        </div>
    @endif

</div>
