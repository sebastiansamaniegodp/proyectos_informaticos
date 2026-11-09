@props([
    // Filas con nombre_corto, ventas, costo y ganancia, de mayor a menor.
    'filas',
    'titulo' => 'Ventas por familia',
])

@php
    use App\Support\Guaranies;

    $filas = collect($filas)->values();
    $maximo = max(1, $filas->max('ventas'));
    $total = max(1, $filas->sum('ventas'));
@endphp

{{-- Barras horizontales apiladas, con la misma codificación que las columnas:
     ganancia desde la base y costo a continuación; el largo es lo vendido. --}}
<figure {{ $attributes->class('m-0') }} x-data="{ activo: null }" x-on:mouseleave="activo = null"
    style="--serie-ganancia: #2a78d6; --serie-costo: #eb6834;">
    <figcaption class="sr-only">{{ $titulo }}: ventas de cada familia, partidas en ganancia y costo.</figcaption>

    <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px] text-[#44443F]" aria-hidden="true">
        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-[var(--serie-ganancia)]"></span>Ganancia</span>
        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-[var(--serie-costo)]"></span>Costo</span>
    </div>

    <ul class="m-0 flex list-none flex-col gap-3 p-0">
        @foreach ($filas as $i => $fila)
            @php
                $ganancia = max(0, $fila->ganancia);
                $costo = max(0, $fila->ventas - $ganancia);
                $largo = max(1, $fila->ventas / $maximo * 100);
                $parte = $fila->ventas / $total * 100;
                $etiqueta = $fila->nombre_corto.': ventas '.Guaranies::completo($fila->ventas).', ganancia '.Guaranies::completo($fila->ganancia).', costo '.Guaranies::completo($fila->costo);
            @endphp
            <li tabindex="0" aria-label="{{ $etiqueta }}" class="relative -mx-2 cursor-default rounded-md px-2 py-1 outline-none focus-visible:bg-[#141413]/5"
                x-on:mouseenter="activo = {{ $i }}" x-on:focus="activo = {{ $i }}" x-on:blur="activo = null"
                x-bind:class="activo === {{ $i }} && 'bg-[#141413]/[0.04]'">
                <div class="flex items-baseline justify-between gap-3 text-[13px]" aria-hidden="true">
                    <span class="min-w-0 truncate font-medium text-[#141413]">{{ $fila->nombre_corto }}</span>
                    <span class="flex-none text-[#44443F] tabular-nums">
                        <span class="font-semibold text-[#141413]">{{ Guaranies::compacto($fila->ventas) }}</span>
                        <span class="text-[#8A8A84]">· {{ Guaranies::porcentaje($parte) }}</span>
                    </span>
                </div>
                <div class="mt-1.5 flex h-3.5 gap-[2px]" style="width: {{ $largo }}%" aria-hidden="true">
                    @if ($ganancia > 0)
                        <div @class(['h-full bg-[var(--serie-ganancia)]', 'rounded-r-[4px]' => $costo == 0]) style="flex: {{ $ganancia }} 1 0"></div>
                    @endif
                    @if ($costo > 0)
                        <div class="h-full rounded-r-[4px] bg-[var(--serie-costo)]" style="flex: {{ $costo }} 1 0"></div>
                    @endif
                </div>

                <div x-show="activo === {{ $i }}" x-cloak x-transition.opacity.duration.100ms
                    class="pointer-events-none absolute right-2 bottom-full z-10 mb-1 w-max min-w-[150px] rounded-lg border border-[#E3E3DE] bg-white px-3 py-2 text-xs shadow-lg">
                    <div class="font-semibold text-[#5A5A54]">{{ $fila->nombre_corto }}</div>
                    <div class="mt-0.5 text-[15px] font-semibold text-[#141413]">{{ Guaranies::completo($fila->ventas) }}</div>
                    <div class="mt-1.5 flex items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-1.5 text-[#44443F]"><span class="h-0.5 w-3 rounded bg-[var(--serie-ganancia)]"></span>Ganancia</span>
                        <span class="font-semibold text-[#141413] tabular-nums">{{ Guaranies::completo($fila->ganancia) }}</span>
                    </div>
                    <div class="mt-0.5 flex items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-1.5 text-[#44443F]"><span class="h-0.5 w-3 rounded bg-[var(--serie-costo)]"></span>Costo</span>
                        <span class="font-semibold text-[#141413] tabular-nums">{{ Guaranies::completo($fila->costo) }}</span>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>

    <details class="mt-3 text-[13px]">
        <summary class="cursor-pointer text-[#44443F] select-none hover:text-[#141413]">Ver los números</summary>
        <div class="mt-2 overflow-x-auto">
            <table class="w-full border-collapse text-left tabular-nums">
                <thead class="text-xs text-[#5A5A54]">
                    <tr>
                        <th class="py-1.5 pr-3 font-medium">Familia</th>
                        <th class="py-1.5 pr-3 text-right font-medium">Ventas</th>
                        <th class="py-1.5 pr-3 text-right font-medium">Costo</th>
                        <th class="py-1.5 text-right font-medium">Ganancia</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filas as $fila)
                        <tr class="border-t border-[#EDEDE8]">
                            <td class="py-1.5 pr-3">{{ $fila->nombre_corto }}</td>
                            <td class="py-1.5 pr-3 text-right">{{ Guaranies::completo($fila->ventas) }}</td>
                            <td class="py-1.5 pr-3 text-right">{{ Guaranies::completo($fila->costo) }}</td>
                            <td class="py-1.5 text-right">{{ Guaranies::completo($fila->ganancia) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
