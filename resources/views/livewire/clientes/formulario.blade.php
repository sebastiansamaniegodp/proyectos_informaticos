@php
    $etiqueta = 'mb-1.5 block text-[13px] font-medium text-[#44443F]';
    $campo = 'min-h-12 w-full rounded-[10px] border border-[#D5D5CF] bg-white px-3 text-[15px] text-[#141413] outline-none focus:border-[#141413] aria-invalid:border-[#B3261E]';
    $tarjeta = 'flex flex-col gap-3 rounded-[10px] border border-[#E3E3DE] bg-white px-4 py-3.5';
    $error = 'mt-1.5 text-[13px] leading-normal text-[#B3261E]';
@endphp

<form wire:submit="guardar" novalidate
    class="flex max-w-[640px] flex-1 flex-col px-4 pt-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    @php $tituloPantalla = $cliente ? 'Editar cliente' : 'Nuevo cliente'; @endphp

    <x-encabezado :titulo="$tituloPantalla"
        :migas="[['Inicio', route('inicio')], ['Clientes', route('clientes.listado')], [$tituloPantalla]]" />

    <div class="mb-4 flex flex-col gap-3.5 sm:mb-0 md:gap-4">

        <div class="{{ $tarjeta }}">
            <div>
                <label for="nombre" class="{{ $etiqueta }}">Nombre</label>
                <input id="nombre" type="text" maxlength="120" autocomplete="off" wire:model="nombre"
                    @unless ($cliente) autofocus @endunless
                    @error('nombre') aria-invalid="true" @enderror class="{{ $campo }}">
                @error('nombre') <div class="{{ $error }}">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="{{ $tarjeta }}">
            <div class="flex flex-col gap-3 sm:flex-row sm:gap-2.5">
                <div class="min-w-0 flex-1">
                    <label for="telefono" class="{{ $etiqueta }}">Teléfono</label>
                    <input id="telefono" type="tel" inputmode="tel" data-solo="telefono" maxlength="30" autocomplete="off" wire:model="telefono"
                        @error('telefono') aria-invalid="true" @enderror class="{{ $campo }}">
                    @error('telefono') <div class="{{ $error }}">{{ $message }}</div> @enderror
                </div>
                <div class="min-w-0 flex-1">
                    <label for="documento" class="{{ $etiqueta }}">Cédula o RUC</label>
                    <input id="documento" type="text" data-solo="documento" maxlength="20" autocomplete="off" wire:model="documento"
                        @error('documento') aria-invalid="true" @enderror class="{{ $campo }}">
                    @error('documento') <div class="{{ $error }}">{{ $message }}</div> @enderror
                </div>
            </div>
            <div>
                <label for="direccion" class="{{ $etiqueta }}">Dirección de entrega</label>
                <input id="direccion" type="text" maxlength="200" autocomplete="off" wire:model="direccion_entrega"
                    @error('direccion_entrega') aria-invalid="true" @enderror class="{{ $campo }}">
                @error('direccion_entrega') <div class="{{ $error }}">{{ $message }}</div> @enderror
            </div>
        </div>

    </div>

    {{-- En el celular el botón queda fijo abajo; desde tablet, a la derecha al final. --}}
    <div class="sticky bottom-0 -mx-4 mt-auto border-t border-[#E3E3DE] bg-white px-4 pt-3 pb-4 sm:static sm:mx-0 sm:mt-4 sm:flex sm:justify-end sm:gap-2.5 sm:border-0 sm:bg-transparent sm:p-0 lg:mt-5">
        <a href="{{ route('clientes.listado') }}" wire:navigate
            class="hidden min-h-11 items-center rounded-[10px] border border-[#D5D5CF] bg-white px-5 text-[15px] font-semibold text-[#141413] no-underline hover:bg-[#FAFAF8] sm:inline-flex">
            Cancelar
        </a>
        <button type="submit" wire:loading.attr="disabled" wire:target="guardar"
            class="flex min-h-[52px] w-full cursor-pointer items-center justify-center rounded-[10px] border-0 bg-[#141413] text-base font-semibold text-white hover:bg-[#2A2A28] disabled:cursor-default disabled:bg-[#5A5A54] sm:min-h-11 sm:w-auto sm:px-6 sm:text-[15px]">
            <span wire:loading.remove wire:target="guardar">Guardar cliente</span>
            <span wire:loading wire:target="guardar">Guardando…</span>
        </button>
    </div>

</form>
