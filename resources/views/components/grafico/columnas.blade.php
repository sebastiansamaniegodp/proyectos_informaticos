@props([
    // Colección de meses con mes (Carbon), ventas, costo, ganancia y pedidos.
    'meses',
    'titulo' => 'Ventas por mes',
])

@php
    use App\Support\Guaranies;

    $meses = collect($meses)->values();
    [$tope, $paso] = Guaranies::escala($meses->max('ventas'));
    $marcas = range(0, $tope, $paso);
    $ultimo = $meses->count() - 1;
    $nombreMes = fn ($mes) => $mes->locale('es')->translatedFormat('F Y');
@endphp

{{-- Columnas apiladas: la altura es lo vendido, partida en ganancia (abajo,
     contra la base, para comparar mes a mes) y costo. Ganancia + costo = ventas. --}}
<figure {{ $attributes->class('m-0') }} x-data="{ activo: null }" x-on:mouseleave="activo = null"
    style="--serie-ganancia: #2a78d6; --serie-costo: #eb6834;">
    <figcaption class="sr-only">{{ $titulo }}: ventas de cada mes, partidas en ganancia y costo.</figcaption>

    <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px] text-[#44443F]" aria-hidden="true">
        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-[var(--serie-ganancia)]"></span>Ganancia</span>
        <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-[var(--serie-costo)]"></span>Costo</span>
        <span class="text-[#8A8A84]">La altura de cada columna es lo vendido.</span>
    </div>

    <div class="relative">
        {{-- Área del gráfico: las marcas del eje a la izquierda y las líneas guía detrás. --}}
        <div class="relative ml-11 h-[180px] sm:h-[200px]">
            @foreach ($marcas as $marca)
                <div class="pointer-events-none absolute inset-x-0 border-t {{ $marca === 0 ? 'border-[#C3C2B7]' : 'border-[#EDEDE8]' }}" style="bottom: {{ $marca / $tope * 100 }}%" aria-hidden="true">
                    <span class="absolute -left-11 w-9 -translate-y-1/2 text-right text-[11px] text-[#8A8A84] tabular-nums">{{ Guaranies::compacto($marca) }}</span>
                </div>
            @endforeach

            <div class="absolute inset-0 grid" style="grid-template-columns: repeat({{ $meses->count() }}, minmax(0, 1fr))">
                @foreach ($meses as $i => $mes)
                    @php
                        $ganancia = max(0, $mes->ganancia);
                        $costo = max(0, $mes->ventas - $ganancia);
                        $alto = $mes->ventas > 0 ? max(1.5, $mes->ventas / $tope * 100) : 0;
                        $etiqueta = ucfirst($nombreMes($mes->mes)).': ventas '.Guaranies::completo($mes->ventas)
                            .', ganancia '.Guaranies::completo($mes->ganancia).', costo '.Guaranies::completo($mes->costo);
                    @endphp
                    <div tabindex="0" role="img" aria-label="{{ $etiqueta }}"
                        x-on:mouseenter="activo = {{ $i }}" x-on:focus="activo = {{ $i }}" x-on:blur="activo = null"
                        class="group relative flex h-full cursor-default items-end justify-center rounded-md outline-none focus-visible:bg-[#141413]/5"
                        x-bind:class="activo === {{ $i }} && 'bg-[#141413]/[0.04]'">
                        @if ($i === $ultimo && $mes->ventas > 0)
                            <span class="pointer-events-none absolute left-1/2 -translate-x-1/2 text-[11px] font-semibold whitespace-nowrap text-[#141413]" style="bottom: calc({{ $alto }}% + 4px)">
                                {{ Guaranies::compacto($mes->ventas) }}
                            </span>
                        @endif
                        @if ($mes->ventas > 0)
                            <div class="flex w-[min(24px,55%)] flex-col-reverse gap-[2px] transition-[filter] group-hover:brightness-110" style="height: {{ $alto }}%">
                                @if ($ganancia > 0)
                                    <div @class(['min-h-[2px] bg-[var(--serie-ganancia)]', 'rounded-t-[4px]' => $costo == 0]) style="flex: {{ $ganancia }} 1 0"></div>
                                @endif
                                @if ($costo > 0)
                                    <div class="min-h-[2px] rounded-t-[4px] bg-[var(--serie-costo)]" style="flex: {{ $costo }} 1 0"></div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Detalle al pasar el dedo o el mouse. Los mismos números están en la tabla de abajo. --}}
            @foreach ($meses as $i => $mes)
                @php
                    // Al costado de la columna, del lado con más lugar, para no taparla.
                    $n = $meses->count();
                    $posicion = $i < $n / 2
                        ? 'left: calc('.(($i + 1) / $n * 100).'% - 4px)'
                        : 'right: calc('.(($n - $i) / $n * 100).'% - 4px)';
                @endphp
                <div x-show="activo === {{ $i }}" x-cloak x-transition.opacity.duration.100ms
                    class="pointer-events-none absolute top-0 z-10 w-max min-w-[150px] rounded-lg border border-[#E3E3DE] bg-white px-3 py-2 text-xs shadow-lg"
                    style="{{ $posicion }}">
                    <div class="font-semibold text-[#5A5A54]">{{ ucfirst($nombreMes($mes->mes)) }}</div>
                    <div class="mt-0.5 text-[15px] font-semibold text-[#141413]">{{ Guaranies::completo($mes->ventas) }}</div>
                    <div class="text-[#5A5A54]">vendido en {{ $mes->pedidos }} {{ $mes->pedidos == 1 ? 'pedido' : 'pedidos' }}</div>
                    <div class="mt-1.5 flex items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-1.5 text-[#44443F]"><span class="h-0.5 w-3 rounded bg-[var(--serie-ganancia)]"></span>Ganancia</span>
                        <span class="font-semibold text-[#141413] tabular-nums">{{ Guaranies::completo($mes->ganancia) }}</span>
                    </div>
                    <div class="mt-0.5 flex items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-1.5 text-[#44443F]"><span class="h-0.5 w-3 rounded bg-[var(--serie-costo)]"></span>Costo</span>
                        <span class="font-semibold text-[#141413] tabular-nums">{{ Guaranies::completo($mes->costo) }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-1.5 ml-11 grid text-center text-[11px] text-[#5A5A54]" style="grid-template-columns: repeat({{ $meses->count() }}, minmax(0, 1fr))" aria-hidden="true">
            @foreach ($meses as $i => $mes)
                <span @class(['font-semibold text-[#141413]' => $i === $ultimo])>{{ $mes->mes->locale('es')->translatedFormat('M') }}</span>
            @endforeach
        </div>
    </div>

    <details class="mt-3 text-[13px]">
        <summary class="cursor-pointer text-[#44443F] select-none hover:text-[#141413]">Ver los números</summary>
        <div class="mt-2 overflow-x-auto">
            <table class="w-full border-collapse text-left tabular-nums">
                <thead class="text-xs text-[#5A5A54]">
                    <tr>
                        <th class="py-1.5 pr-3 font-medium">Mes</th>
                        <th class="py-1.5 pr-3 text-right font-medium">Ventas</th>
                        <th class="py-1.5 pr-3 text-right font-medium">Costo</th>
                        <th class="py-1.5 text-right font-medium">Ganancia</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($meses as $mes)
                        <tr class="border-t border-[#EDEDE8]">
                            <td class="py-1.5 pr-3">{{ ucfirst($mes->mes->locale('es')->translatedFormat('F')) }}</td>
                            <td class="py-1.5 pr-3 text-right">{{ Guaranies::completo($mes->ventas) }}</td>
                            <td class="py-1.5 pr-3 text-right">{{ Guaranies::completo($mes->costo) }}</td>
                            <td class="py-1.5 text-right">{{ Guaranies::completo($mes->ganancia) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
