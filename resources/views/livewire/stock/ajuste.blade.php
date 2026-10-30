@php
    use App\Models\MovimientoStock;
    use App\Models\Stock;

    $etiqueta = 'mb-1.5 block text-[13px] font-medium text-[#44443F]';
    $campo = 'min-h-12 w-full rounded-[10px] border border-[#D5D5CF] bg-white px-3 text-[15px] text-[#141413] outline-none focus:border-[#141413] aria-invalid:border-[#B3261E]';
    $tarjeta = 'flex flex-col gap-3 rounded-[10px] border border-[#E3E3DE] bg-white px-4 py-3.5';
    $opcion = 'flex min-h-11 cursor-pointer items-center justify-center rounded-[10px] border border-[#D5D5CF] bg-white px-3 text-center text-[15px] font-medium text-[#44443F] has-checked:border-[#141413] has-checked:bg-[#141413] has-checked:font-semibold has-checked:text-white';
    $ayuda = 'mt-1.5 text-[13px] leading-normal text-[#5A5A54]';
    $error = 'mt-1.5 text-[13px] leading-normal text-[#B3261E]';

    $inventario = $motivo === 'INVENTARIO';
    $entra = $inventario && $sentido === 'ENTRA';
    $enMetros = $this->enMetros();
@endphp

<form wire:submit="guardar" novalidate
    class="flex max-w-[640px] flex-1 flex-col px-4 pt-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    <x-encabezado titulo="Ajuste de stock"
        :migas="[['Inicio', route('inicio')], ['Stock', route('stock.listado')], [$producto->codigo, route('stock.detalle', $producto)], ['Ajuste']]" />

    <div class="mb-4 flex flex-col gap-3.5 sm:mb-0 md:gap-4">

        <div class="{{ $tarjeta }}">
            <div>
                <div class="text-[15px] font-semibold">{{ $producto->medidas() ?: $producto->descripcion }}</div>
                <div class="mt-0.5 text-sm text-[#5A5A54]">{{ $producto->codigo }}</div>
            </div>
        </div>

        <div class="{{ $tarjeta }}">
            <fieldset class="m-0 border-0 p-0">
                <legend class="{{ $etiqueta }} p-0">Motivo</legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach (MovimientoStock::MOTIVOS_AJUSTE as $valor => $texto)
                        <label class="{{ $opcion }}">
                            <input type="radio" value="{{ $valor }}" wire:model.live="motivo" class="sr-only">
                            {{-- "Diferencia de inventario" no entra en el botón del celular. --}}
                            {{ $valor === 'INVENTARIO' ? 'Inventario' : $texto }}
                        </label>
                    @endforeach
                </div>
                @error('motivo')
                    <div class="{{ $error }}">{{ $message }}</div>
                @else
                    <div class="{{ $ayuda }}">Toda salida que no es una venta lleva su motivo.</div>
                @enderror
            </fieldset>

            @if ($inventario)
                <fieldset class="m-0 border-0 p-0" wire:key="sentido">
                    <legend class="{{ $etiqueta }} p-0">Al contar, ¿qué pasó?</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="{{ $opcion }}">
                            <input type="radio" value="SALE" wire:model.live="sentido" class="sr-only">
                            Falta mercadería
                        </label>
                        <label class="{{ $opcion }}">
                            <input type="radio" value="ENTRA" wire:model.live="sentido" class="sr-only">
                            Sobra mercadería
                        </label>
                    </div>
                </fieldset>
            @endif

            @if ($producto->seCorta())
                <fieldset class="m-0 border-0 p-0" wire:key="que">
                    <legend class="{{ $etiqueta }} p-0">Qué se ajusta</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="{{ $opcion }}">
                            <input type="radio" value="PIEZAS" wire:model.live="que" class="sr-only">
                            Rollos cerrados
                        </label>
                        <label class="{{ $opcion }}">
                            <input type="radio" value="METROS" wire:model.live="que" class="sr-only">
                            Metros
                        </label>
                    </div>
                </fieldset>
            @endif

            @if ($enMetros)
                <div wire:key="rollo">
                    <label for="id_rollo" class="{{ $etiqueta }}">Rollo</label>
                    <select id="id_rollo" wire:model="id_rollo"
                        @error('id_rollo') aria-invalid="true" @enderror class="{{ $campo }}">
                        @if ($entra)
                            <option value="">Elegí el rollo</option>
                        @else
                            <option value="">El que toca, como en una venta</option>
                        @endif
                        @foreach ($this->rollos as $rollo)
                            <option value="{{ $rollo->id_rollo }}">Rollo #{{ $rollo->id_rollo }} · quedan {{ Stock::formatearCantidad($rollo->metros_restantes) }} m de {{ Stock::formatearCantidad($rollo->metros_iniciales) }}</option>
                        @endforeach
                    </select>
                    @error('id_rollo')
                        <div class="{{ $error }}">{{ $message }}</div>
                    @else
                        <div class="{{ $ayuda }}">
                            @if ($entra)
                                Los metros que sobran se suman al rollo abierto que se contó.
                            @else
                                Sin elegir, sale del abierto con menos metros que alcance; si ninguno alcanza, se abre un rollo cerrado.
                            @endif
                        </div>
                    @enderror
                </div>
            @endif

            <div>
                <label for="cantidad" class="{{ $etiqueta }}">
                    {{ $enMetros ? 'Metros' : 'Cantidad de '.$producto->nombrePiezas() }}
                    {{ $entra ? 'que sobran' : 'que salen' }}
                </label>
                <input id="cantidad" type="text" inputmode="{{ $enMetros ? 'decimal' : 'numeric' }}" data-solo="{{ $enMetros ? 'decimal' : 'enteros' }}" maxlength="{{ $enMetros ? 7 : 5 }}" autocomplete="off" wire:model="cantidad"
                    @error('cantidad') aria-invalid="true" @enderror class="{{ $campo }} sm:max-w-[200px]">
                @error('cantidad') <div class="{{ $error }}">{{ $message }}</div> @enderror
            </div>

            <div>
                <label for="observacion" class="{{ $etiqueta }}">Observación <span class="font-normal text-[#5A5A54]">(opcional)</span></label>
                <input id="observacion" type="text" maxlength="200" autocomplete="off" wire:model="observacion"
                    @error('observacion') aria-invalid="true" @enderror class="{{ $campo }}">
                @error('observacion')
                    <div class="{{ $error }}">{{ $message }}</div>
                @else
                    <div class="{{ $ayuda }}">Por ejemplo, a quién se le dio la muestra o cómo se rompió.</div>
                @enderror
            </div>
        </div>

    </div>

    {{-- En el celular el botón queda fijo abajo; desde tablet, a la derecha al final. --}}
    <div class="sticky bottom-0 -mx-4 mt-auto border-t border-[#E3E3DE] bg-white px-4 pt-3 pb-4 sm:static sm:mx-0 sm:mt-4 sm:flex sm:justify-end sm:gap-2.5 sm:border-0 sm:bg-transparent sm:p-0 lg:mt-5">
        <a href="{{ route('stock.detalle', $producto) }}" wire:navigate
            class="hidden min-h-11 items-center rounded-[10px] border border-[#D5D5CF] bg-white px-5 text-[15px] font-semibold text-[#141413] no-underline hover:bg-[#FAFAF8] sm:inline-flex">
            Cancelar
        </a>
        <button type="submit" wire:loading.attr="disabled" wire:target="guardar"
            @class([
                'flex min-h-[52px] w-full cursor-pointer items-center justify-center rounded-[10px] border-0 text-base font-semibold text-white disabled:cursor-default disabled:bg-[#5A5A54] sm:min-h-11 sm:w-auto sm:px-6 sm:text-[15px]',
                'bg-[#2F6B45] hover:bg-[#285C3B]' => $entra,
                'bg-[#B3261E] hover:bg-[#9A2019]' => ! $entra,
            ])>
            <span wire:loading.remove wire:target="guardar">{{ $entra ? 'Sumar al stock' : 'Descontar del stock' }}</span>
            <span wire:loading wire:target="guardar">Guardando…</span>
        </button>
    </div>

</form>
