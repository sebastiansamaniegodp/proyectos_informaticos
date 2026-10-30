@php
    use App\Models\MovimientoStock;

    $etiqueta = 'mb-1.5 block text-[13px] font-medium text-[#44443F]';
    $campo = 'min-h-12 w-full rounded-[10px] border border-[#D5D5CF] bg-white px-3 text-[15px] text-[#141413] outline-none focus:border-[#141413] aria-invalid:border-[#B3261E]';
    $tarjeta = 'flex flex-col gap-3 rounded-[10px] border border-[#E3E3DE] bg-white px-4 py-3.5';
    $opcion = 'flex min-h-11 flex-1 cursor-pointer items-center justify-center rounded-[10px] border border-[#D5D5CF] bg-white px-3 text-[15px] font-medium text-[#44443F] has-checked:border-[#141413] has-checked:bg-[#141413] has-checked:font-semibold has-checked:text-white';
    $ayuda = 'mt-1.5 text-[13px] leading-normal text-[#5A5A54]';
    $error = 'mt-1.5 text-[13px] leading-normal text-[#B3261E]';
@endphp

<form wire:submit="guardar" novalidate
    class="flex max-w-[640px] flex-1 flex-col px-4 pt-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    <x-encabezado titulo="Ingreso de stock"
        :migas="[['Inicio', route('inicio')], ['Stock', route('stock.listado')], [$producto->codigo, route('stock.detalle', $producto)], ['Ingreso']]" />

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
                <div class="flex gap-2">
                    @foreach (MovimientoStock::MOTIVOS_INGRESO as $valor => $texto)
                        <label class="{{ $opcion }}">
                            <input type="radio" value="{{ $valor }}" wire:model.live="motivo" class="sr-only">
                            {{ $texto }}
                        </label>
                    @endforeach
                </div>
                @error('motivo') <div class="{{ $error }}">{{ $message }}</div> @enderror
            </fieldset>

            <div>
                <label for="cantidad" class="{{ $etiqueta }}">Cantidad de {{ $producto->nombrePiezas() }}</label>
                <input id="cantidad" type="text" inputmode="numeric" data-solo="enteros" maxlength="5" autocomplete="off" wire:model="cantidad" autofocus
                    @error('cantidad') aria-invalid="true" @enderror class="{{ $campo }} sm:max-w-[200px]">
                @error('cantidad')
                    <div class="{{ $error }}">{{ $message }}</div>
                @else
                    @if ($producto->seCorta())
                        <div class="{{ $ayuda }}">Rollos enteros, sin abrir, de {{ $producto->metraje_rollo }} m cada uno.</div>
                    @endif
                @enderror
            </div>

            <div>
                <label for="observacion" class="{{ $etiqueta }}">Observación <span class="font-normal text-[#5A5A54]">(opcional)</span></label>
                <input id="observacion" type="text" maxlength="200" autocomplete="off" wire:model="observacion"
                    @error('observacion') aria-invalid="true" @enderror class="{{ $campo }}">
                @error('observacion')
                    <div class="{{ $error }}">{{ $message }}</div>
                @else
                    <div class="{{ $ayuda }}">Por ejemplo, el proveedor o el número de remito.</div>
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
            class="flex min-h-[52px] w-full cursor-pointer items-center justify-center rounded-[10px] border-0 bg-[#141413] text-base font-semibold text-white hover:bg-[#2A2A28] disabled:cursor-default disabled:bg-[#5A5A54] sm:min-h-11 sm:w-auto sm:px-6 sm:text-[15px]">
            <span wire:loading.remove wire:target="guardar">Guardar ingreso</span>
            <span wire:loading wire:target="guardar">Guardando…</span>
        </button>
    </div>

</form>
