@php
    use App\Models\PedidoDetalle;
    use App\Models\Producto;
    use App\Models\RolloAbierto;
    use App\Services\PedidoService;

    $etiqueta = 'mb-1.5 block text-[13px] font-medium text-[#44443F]';
    $campo = 'min-h-12 w-full rounded-[10px] border border-[#D5D5CF] bg-white px-3 text-[15px] text-[#141413] outline-none focus:border-[#141413] aria-invalid:border-[#B3261E]';
    $tarjeta = 'rounded-[10px] border border-[#E3E3DE] bg-white';
    $titulo = 'm-0 font-titulo text-base font-semibold';
    $opcion = 'flex min-h-12 flex-1 cursor-pointer flex-col items-center justify-center rounded-[10px] border border-[#D5D5CF] bg-white px-3 py-1.5 text-center text-[15px] font-medium text-[#44443F] has-checked:border-[#141413] has-checked:bg-[#141413] has-checked:text-white';
    $ayuda = 'mt-1.5 text-[13px] leading-normal text-[#5A5A54]';
    $error = 'mt-1.5 text-[13px] leading-normal text-[#B3261E]';
    $botonNegro = 'flex min-h-[52px] flex-1 cursor-pointer items-center justify-center rounded-[10px] border-0 bg-[#141413] px-5 text-base font-semibold text-white hover:bg-[#2A2A28] disabled:cursor-default disabled:bg-[#5A5A54] sm:min-h-11 sm:flex-none sm:text-[15px]';
    $botonBlanco = 'flex min-h-[52px] flex-1 cursor-pointer items-center justify-center rounded-[10px] border border-[#D5D5CF] bg-white px-5 text-base font-semibold text-[#141413] no-underline hover:bg-[#FAFAF8] sm:min-h-11 sm:flex-none sm:text-[15px]';
    $titulo_pantalla = $pedido ? 'Pedido N° '.$pedido->numero() : 'Nuevo pedido';
    $migas = [['Inicio', route('inicio')], ['Pedidos', route('pedidos.listado')], [$pedido ? 'N° '.$pedido->numero() : 'Nuevo']];
@endphp

<div class="flex max-w-[760px] flex-1 flex-col px-4 pt-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

@if ($panelLinea)
    {{-- Panel para agregar o cambiar una línea ("Agregar línea al pedido" del prototipo). --}}
    @php
        $producto = $this->producto;
        $calculo = $this->calculo;
        $corte = $producto?->seCorta() && $forma === 'METRO';
    @endphp

    <x-encabezado :titulo="$indiceLinea === null ? 'Agregar producto' : 'Cambiar línea'" :migas="array_merge(array_slice($migas, 0, 2), [[$titulo_pantalla], ['Producto']])" />

    <form wire:submit="guardarLinea" novalidate class="mb-4 flex flex-col gap-3.5 sm:mb-0">

        <section class="{{ $tarjeta }} px-4 py-3.5">
            @if ($producto)
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[13px] font-medium text-[#44443F]">Producto</div>
                        <div class="mt-1 text-[15px] font-semibold">{{ $producto->nombreCompleto() }}</div>
                        <div class="mt-0.5 text-[13px] text-[#5A5A54]">
                            {{ $producto->codigo }}@if ($producto->seCorta()) · rollos de {{ $producto->metraje_rollo }} m @endif
                        </div>
                    </div>
                    <button type="button" wire:click="cambiarProducto" class="min-h-11 flex-none cursor-pointer rounded-[10px] border border-[#D5D5CF] bg-white px-3.5 text-sm font-semibold text-[#141413] hover:bg-[#FAFAF8]">Cambiar</button>
                </div>
                <div class="mt-2.5 rounded-lg bg-[#F4F4F1] px-3 py-2 text-[13px] text-[#44443F]">
                    Disponible: <span class="font-semibold text-[#141413]">{{ PedidoService::disponible($producto) }}</span>
                </div>
            @else
                <label for="busqueda-producto" class="{{ $etiqueta }}">Producto</label>
                <input id="busqueda-producto" type="search" wire:model.live.debounce.250ms="busquedaProducto" autofocus
                    placeholder="Buscar por nombre o código, por ejemplo hexagonal" autocomplete="off"
                    @error('producto') aria-invalid="true" @enderror class="{{ $campo }}">
                @error('producto') <div class="{{ $error }}">{{ $message }}</div> @enderror

                @if (trim($busquedaProducto) !== '')
                    <ul class="m-0 mt-2.5 list-none overflow-hidden rounded-[10px] border border-[#EDEDE8] p-0">
                        @forelse ($this->productos as $item)
                            <li wire:key="buscado-{{ $item->id_producto }}">
                                <button type="button" wire:click="elegirProducto({{ $item->id_producto }})"
                                    class="flex w-full cursor-pointer items-center justify-between gap-3 border-0 border-t border-[#EDEDE8] bg-white px-3 py-2.5 text-left first:border-t-0 hover:bg-[#FAFAF8]">
                                    <span class="min-w-0">
                                        <span class="block text-[15px] font-medium text-[#141413]">{{ $item->nombreCompleto() }}</span>
                                        <span class="mt-0.5 block text-xs text-[#5A5A54]">{{ $item->codigo }} · {{ PedidoService::disponible($item) }}</span>
                                    </span>
                                    <span class="flex-none text-sm font-semibold text-[#141413]">{{ Producto::formatearPrecio($item->precio_venta) }}</span>
                                </button>
                            </li>
                        @empty
                            <li class="px-3 py-3 text-sm text-[#5A5A54]">No hay productos que coincidan con la búsqueda.</li>
                        @endforelse
                    </ul>
                @else
                    <div class="{{ $ayuda }}">Escribí parte del nombre o del código.</div>
                @endif
            @endif
        </section>

        @if ($producto)
            <section class="{{ $tarjeta }} flex flex-col gap-4 px-4 py-3.5">
                @if ($producto->seCorta())
                    <fieldset class="m-0 border-0 p-0">
                        <legend class="{{ $etiqueta }} p-0">Cómo se vende</legend>
                        <div class="flex gap-2">
                            <label class="{{ $opcion }}">
                                <input type="radio" value="PIEZA" wire:model.live="forma" class="sr-only">
                                <span class="font-semibold">Rollo entero</span>
                                <span class="text-[13px] opacity-80">{{ Producto::formatearPrecio($producto->precio_venta) }}</span>
                            </label>
                            <label class="{{ $opcion }}">
                                <input type="radio" value="METRO" wire:model.live="forma" class="sr-only">
                                <span class="font-semibold">Por metro</span>
                                <span class="text-[13px] opacity-80">{{ Producto::formatearPrecio($producto->precio_metro) }} el metro</span>
                            </label>
                        </div>
                    </fieldset>
                @endif

                @if (! $corte && $producto->precio_mayorista)
                    <fieldset class="m-0 border-0 p-0">
                        <legend class="{{ $etiqueta }} p-0">Precio</legend>
                        <div class="flex gap-2">
                            <label class="{{ $opcion }}">
                                <input type="radio" value="LISTA" wire:model.live="precio" class="sr-only">
                                <span class="font-semibold">De lista</span>
                                <span class="text-[13px] opacity-80">{{ Producto::formatearPrecio($producto->precio_venta) }}</span>
                            </label>
                            <label class="{{ $opcion }}">
                                <input type="radio" value="MAYORISTA" wire:model.live="precio" class="sr-only">
                                <span class="font-semibold">Mayorista</span>
                                <span class="text-[13px] opacity-80">{{ Producto::formatearPrecio($producto->precio_mayorista) }}</span>
                            </label>
                        </div>
                    </fieldset>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="cantidad" class="{{ $etiqueta }}">{{ $corte ? 'Cuántos metros' : 'Cuántos '.$producto->nombrePiezas() }}</label>
                        <input id="cantidad" type="text" inputmode="decimal" data-solo="{{ $corte ? 'decimal' : 'enteros' }}" maxlength="8" autocomplete="off"
                            wire:model.live.debounce.300ms="cantidad"
                            @error('cantidad') aria-invalid="true" @enderror class="{{ $campo }}">
                        @error('cantidad')
                            <div class="{{ $error }}">{{ $message }}</div>
                        @else
                            @if ($corte)
                                <div class="{{ $ayuda }}">Un corte sale de un solo rollo de {{ $producto->metraje_rollo }} m.</div>
                            @endif
                        @enderror
                    </div>

                    <div>
                        <label for="descuento" class="{{ $etiqueta }}">Descuento % <span class="font-normal text-[#5A5A54]">(opcional)</span></label>
                        <input id="descuento" type="text" inputmode="decimal" data-solo="decimal" maxlength="4" autocomplete="off" placeholder="0"
                            wire:model.live.debounce.300ms="descuento" @disabled($corte && $retazo)
                            @error('descuento') aria-invalid="true" @enderror class="{{ $campo }} disabled:bg-[#F4F4F1] disabled:text-[#8A8A84]">
                        @error('descuento')
                            <div class="{{ $error }}">{{ $message }}</div>
                        @else
                            <div class="{{ $ayuda }}">El máximo autorizado es {{ PedidoService::DESCUENTO_MAXIMO }}%.@if ($corte) No se acumula con el de retazo.@endif</div>
                        @enderror
                    </div>
                </div>

                @if ($corte)
                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-[10px] border border-[#D5D5CF] px-3 py-2 has-checked:border-[#141413]">
                        <input type="checkbox" wire:model.live="retazo" class="size-5 flex-none accent-[#141413]">
                        <span>
                            <span class="block text-[15px] font-medium">Es un retazo ({{ RolloAbierto::DESCUENTO_RETAZO }}% de descuento)</span>
                            <span class="block text-[13px] text-[#5A5A54]">Sobrante de menos de {{ RolloAbierto::METROS_RETAZO }} m de un rollo abierto.</span>
                        </span>
                    </label>
                @endif

                @if ($calculo)
                    @php $linea = $calculo['linea']; @endphp
                    <dl class="m-0 rounded-[10px] bg-[#F4F4F1] px-3.5 py-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-[#44443F]">{{ PedidoDetalle::escribirCantidad($linea['cantidad'], $linea['tipo_venta']) }} × {{ Producto::formatearPrecio($linea['precio_unitario']) }}</dt>
                            <dd class="m-0">{{ Producto::formatearPrecio((int) round($calculo['bruto'])) }}</dd>
                        </div>
                        @if ($linea['descuento_pct'] > 0)
                            <div class="mt-1 flex justify-between gap-3">
                                <dt class="text-[#44443F]">menos {{ str_replace('.', ',', (string) (float) $linea['descuento_pct']) }}%</dt>
                                <dd class="m-0">{{ Producto::formatearPrecio((int) round($calculo['neto'])) }}</dd>
                            </div>
                        @endif
                        <div class="mt-2 flex justify-between gap-3 border-t border-[#E3E3DE] pt-2 text-[15px] font-semibold">
                            <dt>Redondeado</dt>
                            <dd class="m-0">{{ Producto::formatearPrecio($calculo['subtotal']) }}</dd>
                        </div>
                    </dl>
                @endif
            </section>
        @endif

        <div class="sticky bottom-0 -mx-4 mt-auto flex gap-2.5 border-t border-[#E3E3DE] bg-white px-4 pt-3 pb-4 sm:static sm:mx-0 sm:justify-end sm:border-0 sm:bg-transparent sm:p-0">
            <button type="button" wire:click="cerrarLinea" class="{{ $botonBlanco }}">Volver</button>
            @if ($indiceLinea !== null)
                <button type="button" wire:click="quitarLinea" class="{{ $botonBlanco }} text-[#B3261E]">Quitar</button>
            @endif
            <button type="submit" @disabled(! $producto) wire:loading.attr="disabled" wire:target="guardarLinea" class="{{ $botonNegro }}">
                {{ $indiceLinea === null ? 'Agregar al pedido' : 'Guardar cambios' }}
            </button>
        </div>
    </form>

@else
    {{-- El pedido: cliente, líneas, flete y totales ("Pedido en borrador" del prototipo). --}}
    @php
        $cliente = $this->cliente;
        $totales = $this->totales;
    @endphp

    <x-encabezado :titulo="$titulo_pantalla" :migas="$migas" />

    <div class="mb-4 flex flex-col gap-3.5 sm:mb-0">

        <div class="flex items-center gap-2 text-[13px] text-[#5A5A54]">
            <x-pedido.estado estado="BORRADOR" />
            <span>Mientras esté en borrador no reserva mercadería.</span>
        </div>

        <section class="{{ $tarjeta }} px-4 py-3.5">
            @if ($cliente)
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[13px] font-medium text-[#44443F]">Cliente</div>
                        <div class="mt-1 text-[15px] font-semibold">{{ $cliente->nombre }}</div>
                        <div class="mt-0.5 text-[13px] text-[#5A5A54]">{{ $cliente->contacto() }}</div>
                    </div>
                    <button type="button" wire:click="cambiarCliente" class="min-h-11 flex-none cursor-pointer rounded-[10px] border border-[#D5D5CF] bg-white px-3.5 text-sm font-semibold text-[#141413] hover:bg-[#FAFAF8]">Cambiar</button>
                </div>
            @else
                <label for="busqueda-cliente" class="{{ $etiqueta }}">Cliente</label>
                <input id="busqueda-cliente" type="search" wire:model.live.debounce.250ms="busquedaCliente"
                    placeholder="Buscar por nombre o teléfono" autocomplete="off"
                    @error('cliente') aria-invalid="true" @enderror class="{{ $campo }}">
                @error('cliente') <div class="{{ $error }}">{{ $message }}</div> @enderror

                <ul class="m-0 mt-2.5 list-none overflow-hidden rounded-[10px] border border-[#EDEDE8] p-0">
                    @forelse ($this->clientes as $item)
                        <li wire:key="cliente-{{ $item->id_cliente }}">
                            <button type="button" wire:click="elegirCliente({{ $item->id_cliente }})"
                                class="block w-full cursor-pointer border-0 border-t border-[#EDEDE8] bg-white px-3 py-2.5 text-left first:border-t-0 hover:bg-[#FAFAF8]">
                                <span class="block text-[15px] font-medium text-[#141413]">{{ $item->nombre }}</span>
                                <span class="mt-0.5 block text-xs text-[#5A5A54]">{{ $item->contacto() }}</span>
                            </button>
                        </li>
                    @empty
                        <li class="px-3 py-3 text-sm text-[#5A5A54]">No hay clientes que coincidan.</li>
                    @endforelse
                </ul>
                <a href="{{ route('clientes.nuevo') }}" wire:navigate class="mt-2.5 inline-flex min-h-11 items-center text-sm font-semibold text-[#141413] underline-offset-2 hover:underline">+ Cargar un cliente nuevo</a>
            @endif
        </section>

        <section class="{{ $tarjeta }} overflow-hidden">
            <h2 class="{{ $titulo }} px-4 pt-3.5 pb-2">Líneas</h2>
            <ul class="m-0 list-none p-0">
                @foreach ($this->filas as $fila)
                    @php
                        $linea = $fila->linea;
                        $detalle = $linea['tipo_venta'] === 'METRO'
                            ? PedidoDetalle::escribirCantidad($linea['cantidad'], 'METRO').' × '.Producto::formatearPrecio($linea['precio_unitario'])
                            : PedidoDetalle::escribirCantidad($linea['cantidad'], $linea['tipo_venta']).($linea['tipo_venta'] === 'ROLLO' && $linea['cantidad'] == 1 ? ' entero' : '').' × '.Producto::formatearPrecio($linea['precio_unitario']);
                        if ($linea['precio'] === 'MAYORISTA') $detalle .= ' · mayorista';
                        if ($linea['descuento_pct'] == RolloAbierto::DESCUENTO_RETAZO && $linea['tipo_venta'] === 'METRO') $detalle .= ' · retazo '.RolloAbierto::DESCUENTO_RETAZO.'%';
                        elseif ($linea['descuento_pct'] > 0) $detalle .= ' · descuento '.str_replace('.', ',', (string) (float) $linea['descuento_pct']).'%';
                    @endphp
                    <li wire:key="linea-{{ $fila->indice }}-{{ $linea['id_producto'] }}">
                        <button type="button" wire:click="abrirLinea({{ $fila->indice }})"
                            class="flex w-full cursor-pointer items-start justify-between gap-3 border-0 border-t border-[#EDEDE8] bg-white px-4 py-3 text-left hover:bg-[#FAFAF8]">
                            <span class="min-w-0">
                                <span class="block text-[15px] font-medium text-[#141413]">{{ $fila->producto->nombreCompleto() }}</span>
                                <span class="mt-0.5 block text-[13px] text-[#5A5A54]">{{ $detalle }}</span>
                            </span>
                            <span class="flex-none text-[15px] font-semibold text-[#141413]">{{ Producto::formatearPrecio($fila->subtotal) }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-[#EDEDE8] px-4 py-3">
                <button type="button" wire:click="abrirLinea"
                    class="flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-[10px] border border-[#D5D5CF] bg-[#FAFAF8] text-[15px] font-semibold text-[#141413] hover:bg-[#F4F4F1]">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Agregar producto
                </button>
                @error('lineas') <div class="{{ $error }}">{{ $message }}</div> @enderror
            </div>
        </section>

        <section class="{{ $tarjeta }} grid gap-4 px-4 py-3.5 sm:grid-cols-[200px_minmax(0,1fr)]">
            <div>
                <label for="flete" class="{{ $etiqueta }}">Flete</label>
                <input id="flete" type="text" inputmode="numeric" data-solo="numeros" maxlength="11" autocomplete="off"
                    wire:model.live.debounce.400ms="flete" @error('flete') aria-invalid="true" @enderror class="{{ $campo }}">
                @error('flete')
                    <div class="{{ $error }}">{{ $message }}</div>
                @else
                    <div class="{{ $ayuda }}">A mano, según la distancia. No lleva descuento.</div>
                @enderror
            </div>
            <div>
                <label for="observacion" class="{{ $etiqueta }}">Observación <span class="font-normal text-[#5A5A54]">(opcional)</span></label>
                <input id="observacion" type="text" maxlength="400" autocomplete="off" wire:model="observacion"
                    placeholder="Por ejemplo, entregar después de las 14 h" @error('observacion') aria-invalid="true" @enderror class="{{ $campo }}">
                @error('observacion') <div class="{{ $error }}">{{ $message }}</div> @enderror
            </div>
        </section>

        <section class="{{ $tarjeta }} px-4 py-3.5">
            <dl class="m-0 text-[15px]">
                <div class="flex justify-between gap-3"><dt class="text-[#44443F]">Subtotal</dt><dd class="m-0">{{ Producto::formatearPrecio($totales['subtotal']) }}</dd></div>
                <div class="mt-1 flex justify-between gap-3"><dt class="text-[#44443F]">Flete</dt><dd class="m-0">{{ Producto::formatearPrecio($totales['flete']) }}</dd></div>
                <div class="mt-2 flex justify-between gap-3 border-t border-[#EDEDE8] pt-2 text-lg font-semibold"><dt>Total</dt><dd class="m-0">{{ Producto::formatearPrecio($totales['total']) }}</dd></div>
            </dl>
        </section>

        @error('confirmar')
            <div role="alert" class="rounded-[10px] border border-[#B3261E]/30 bg-[#B3261E]/6 px-4 py-3 text-sm leading-normal font-medium text-[#B3261E]">{{ $message }}</div>
        @enderror

        <p class="m-0 text-[13px] leading-normal text-[#5A5A54]">
            Al confirmar se descuenta el stock: las piezas enteras salen de las cerradas y cada corte, de un rollo abierto que alcance o de uno nuevo.
        </p>

        @if ($pedido)
            <div>
                <button type="button" wire:click="anular" wire:confirm="¿Anular el pedido N° {{ $pedido->numero() }}? No se puede deshacer."
                    class="min-h-11 cursor-pointer rounded-[10px] border-0 bg-transparent px-0 text-[15px] font-semibold text-[#B3261E] underline-offset-2 hover:underline">
                    Anular pedido
                </button>
            </div>
        @endif
    </div>

    {{-- En el celular los botones quedan fijos abajo; desde tablet, a la derecha al final. --}}
    <div class="sticky bottom-0 -mx-4 mt-auto flex gap-2.5 border-t border-[#E3E3DE] bg-white px-4 pt-3 pb-4 sm:static sm:mx-0 sm:mt-4 sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 lg:mt-5">
        <button type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar,confirmar" class="{{ $botonBlanco }}">
            <span wire:loading.remove wire:target="guardar">Guardar borrador</span>
            <span wire:loading wire:target="guardar">Guardando…</span>
        </button>
        <button type="button" wire:click="confirmar" wire:loading.attr="disabled" wire:target="guardar,confirmar" class="{{ $botonNegro }}">
            <span wire:loading.remove wire:target="confirmar">Confirmar</span>
            <span wire:loading wire:target="confirmar">Confirmando…</span>
        </button>
    </div>
@endif

</div>
