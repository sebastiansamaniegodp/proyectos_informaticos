@php
    $etiqueta = 'mb-1.5 block text-[13px] font-medium text-[#44443F]';
    $campo = 'min-h-12 w-full rounded-[10px] border border-[#D5D5CF] bg-white px-3 text-[15px] text-[#141413] outline-none focus:border-[#141413] aria-invalid:border-[#B3261E]';
    $campoPrecio = str_replace('text-[15px]', 'text-[17px] font-semibold', $campo);
    $tarjeta = 'flex flex-col gap-3 rounded-[10px] border border-[#E3E3DE] bg-white px-4 py-3.5';
    $error = 'mt-1.5 text-[13px] leading-normal text-[#B3261E]';
    $precioUnidad = [
        'ROLLO' => 'Precio del rollo',
        'METRO' => 'Precio del metro',
        'KILO' => 'Precio del kilo',
        'UNIDAD' => 'Precio por unidad',
    ][$unidad_venta] ?? 'Precio';
@endphp

<form wire:submit="guardar" novalidate x-data="fotoProducto"
    class="flex max-w-[1000px] flex-1 flex-col px-4 pt-4 sm:pb-6 lg:px-8 lg:pt-6 lg:pb-10">

    @php $tituloPantalla = $producto ? 'Editar producto' : 'Nuevo producto'; @endphp

    <x-encabezado :titulo="$tituloPantalla"
        :migas="[['Inicio', route('inicio')], ['Catálogo', route('productos.catalogo')], [$tituloPantalla]]" />

    {{-- En el celular las tarjetas van una abajo de otra; desde tablet, en dos columnas. --}}
    <div class="mb-4 flex flex-col gap-3.5 sm:mb-0 md:grid md:grid-cols-2 md:items-start md:gap-4">

        <div class="flex flex-col gap-3.5 md:gap-4">

        <div class="{{ $tarjeta }}">
            <div>
                <label for="familia" class="{{ $etiqueta }}">Familia</label>
                <select id="familia" wire:model.live="id_familia" @error('id_familia') aria-invalid="true" @enderror class="{{ $campo }}">
                    <option value="">Elegí la familia</option>
                    @foreach ($this->familias as $item)
                        <option value="{{ $item->id_familia }}">{{ $item->nombre }}</option>
                    @endforeach
                </select>
                @error('id_familia') <div class="{{ $error }}">{{ $message }}</div> @enderror
            </div>
            <div class="flex gap-2.5">
                <div class="min-w-0 flex-1">
                    <label for="altura" class="{{ $etiqueta }}">Altura (cm)</label>
                    <input id="altura" type="text" inputmode="numeric" wire:model.live.debounce.400ms="altura" @error('altura') aria-invalid="true" @enderror class="{{ $campo }}">
                    @error('altura') <div class="{{ $error }}">{{ $message }}</div> @enderror
                </div>
                <div class="min-w-0 flex-1">
                    <label for="abertura" class="{{ $etiqueta }}">Abertura</label>
                    <input id="abertura" type="text" maxlength="20" wire:model.live.debounce.400ms="abertura" @error('abertura') aria-invalid="true" @enderror class="{{ $campo }}">
                    @error('abertura') <div class="{{ $error }}">{{ $message }}</div> @enderror
                </div>
            </div>
            <div>
                <label for="calibre" class="{{ $etiqueta }}">Calibre</label>
                <input id="calibre" type="text" maxlength="10" wire:model.live.debounce.400ms="calibre" @error('calibre') aria-invalid="true" @enderror class="{{ $campo }}">
                @error('calibre') <div class="{{ $error }}">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="{{ $tarjeta }}">
            <div>
                <label for="unidad" class="{{ $etiqueta }}">Unidad de venta</label>
                <select id="unidad" wire:model.live="unidad_venta" class="{{ $campo }}">
                    @foreach (App\Models\Producto::UNIDADES as $valor => $nombre)
                        <option value="{{ $valor }}">{{ $nombre }}</option>
                    @endforeach
                </select>
                @error('unidad_venta') <div class="{{ $error }}">{{ $message }}</div> @enderror
            </div>
            @if ($this->esRollo())
                <div class="flex items-center gap-2.5 py-1">
                    <input id="fracc" type="checkbox" wire:model.live="fraccionable" class="size-5 accent-[#141413]">
                    <label for="fracc" class="text-[15px]">Se puede cortar por metro</label>
                </div>
                <div>
                    <label for="metraje" class="{{ $etiqueta }}">Metros que trae el rollo</label>
                    <input id="metraje" type="text" inputmode="numeric" wire:model.live.debounce.400ms="metraje" @error('metraje') aria-invalid="true" @enderror class="{{ $campo }}">
                    @error('metraje') <div class="{{ $error }}">{{ $message }}</div> @enderror
                </div>
            @endif
        </div>

        </div>

        <div class="flex flex-col gap-3.5 md:gap-4">

        <div class="{{ $tarjeta }}">
            <div>
                <label for="precio" class="{{ $etiqueta }}">{{ $precioUnidad }}</label>
                <input id="precio" type="text" inputmode="numeric" wire:model.live.blur="precio_venta" @error('precio_venta') aria-invalid="true" @enderror class="{{ $campoPrecio }}">
                @error('precio_venta') <div class="{{ $error }}">{{ $message }}</div> @enderror
            </div>
            @if ($this->seCorta())
                <div>
                    <label for="pmetro" class="{{ $etiqueta }}">Precio por metro</label>
                    <input id="pmetro" type="text" inputmode="numeric" wire:model.live.blur="precio_metro" @error('precio_metro') aria-invalid="true" @enderror class="{{ $campoPrecio }}">
                    @error('precio_metro') <div class="{{ $error }}">{{ $message }}</div> @enderror
                </div>
            @endif
            <div class="flex gap-2.5">
                <div class="min-w-0 flex-1">
                    <label for="pmay" class="{{ $etiqueta }}">Mayorista</label>
                    <input id="pmay" type="text" inputmode="numeric" wire:model.live.blur="precio_mayorista" @error('precio_mayorista') aria-invalid="true" @enderror class="{{ $campo }}">
                    @error('precio_mayorista') <div class="{{ $error }}">{{ $message }}</div> @enderror
                </div>
                <div class="min-w-0 flex-1">
                    <label for="costo" class="{{ $etiqueta }}">Costo</label>
                    <input id="costo" type="text" inputmode="numeric" wire:model.live.blur="costo" @error('costo') aria-invalid="true" @enderror class="{{ $campo }}">
                    @error('costo') <div class="{{ $error }}">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        <div class="rounded-[10px] border border-[#E3E3DE] bg-white px-4 py-3.5">
            <div class="mb-2 text-[13px] font-medium text-[#44443F]">Foto del producto</div>
            <div class="flex items-center gap-3">
                @if ($foto && ! $errors->has('foto'))
                    <img src="{{ $foto->temporaryUrl() }}" alt="Foto nueva del producto" class="size-16 flex-none rounded-lg object-cover">
                @elseif ($producto?->fotoUrl())
                    <img src="{{ $producto->fotoUrl() }}" alt="Foto cargada del producto" class="size-16 flex-none rounded-lg object-cover">
                @else
                    <span class="flex size-16 flex-none items-center justify-center rounded-lg bg-[#EDEDE8] text-[#A8A8A2]" aria-hidden="true">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h3l2-3h6l2 3h3v13H4z"/><circle cx="12" cy="13" r="3.5"/></svg>
                    </span>
                @endif

                <input id="foto" type="file" accept="image/*" class="sr-only" x-ref="archivo"
                    x-on:change="subir($event.target.files[0]); $event.target.value = ''">
                <label for="foto" x-bind:aria-disabled="subiendo"
                    class="flex min-h-11 flex-1 cursor-pointer items-center justify-center rounded-[10px] border border-[#D5D5CF] bg-white text-sm font-semibold text-[#141413] hover:bg-[#FAFAF8] aria-disabled:pointer-events-none aria-disabled:text-[#A8A8A2]">
                    <span x-show="subiendo" x-cloak>Subiendo foto…</span>
                    <span x-show="! subiendo">{{ $foto || $producto?->foto ? 'Cambiar foto' : 'Agregar foto' }}</span>
                </label>
            </div>
            <div x-show="error" x-text="error" x-cloak class="{{ $error }}"></div>
            @error('foto') <div class="{{ $error }}">{{ $message }}</div> @enderror
        </div>

        <div class="rounded-[10px] bg-[#141413] px-4 py-3.5 text-white">
            <div class="text-xs font-semibold tracking-[0.06em] text-[#B5B5AE] uppercase">Código</div>
            <div class="mt-1 font-titulo text-xl font-bold break-all" aria-live="polite">{{ $this->codigo ?? '—' }}</div>
            @error('codigo')
                <div role="alert" class="mt-1.5 text-[13px] leading-normal text-[#E0C3C0]">{{ $message }}</div>
            @else
                <div class="mt-1.5 text-[13px] leading-normal text-[#B5B5AE]">Se arma solo con el tipo, la abertura (o el calibre si no tiene), la altura y la presentación.</div>
            @enderror
        </div>

        </div>

    </div>

    {{-- En el celular el botón queda fijo abajo; desde tablet, a la derecha al final. --}}
    <div class="sticky bottom-0 -mx-4 mt-auto border-t border-[#E3E3DE] bg-white px-4 pt-3 pb-4 sm:static sm:mx-0 sm:mt-4 sm:flex sm:justify-end sm:gap-2.5 sm:border-0 sm:bg-transparent sm:p-0 lg:mt-5">
        <a href="{{ route('productos.catalogo') }}" wire:navigate
            class="hidden min-h-11 items-center rounded-[10px] border border-[#D5D5CF] bg-white px-5 text-[15px] font-semibold text-[#141413] no-underline hover:bg-[#FAFAF8] sm:inline-flex">
            Cancelar
        </a>
        <button type="submit" wire:loading.attr="disabled" wire:target="guardar" x-bind:disabled="subiendo"
            class="flex min-h-[52px] w-full cursor-pointer items-center justify-center rounded-[10px] border-0 bg-[#141413] text-base font-semibold text-white hover:bg-[#2A2A28] disabled:cursor-default disabled:bg-[#5A5A54] sm:min-h-11 sm:w-auto sm:px-6 sm:text-[15px]">
            <span wire:loading.remove wire:target="guardar">Guardar producto</span>
            <span wire:loading wire:target="guardar">Guardando…</span>
        </button>
    </div>

</form>
