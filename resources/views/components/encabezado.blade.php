@props([
    'titulo',
    // Cada miga es [texto, url]; la última, la pantalla actual, va sin url.
    'migas' => [],
])

{{-- Encabezado de la pantalla al estilo AdminLTE: título a la izquierda y
     migas de pan a la derecha (abajo del título en el celular). --}}
<div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4 lg:mb-5">
    <h1 class="m-0 font-titulo text-[22px] font-bold lg:text-[26px]">{{ $titulo }}</h1>

    @if ($migas)
        <nav aria-label="Ubicación">
            <ol class="m-0 flex list-none flex-wrap items-center gap-1.5 p-0 text-[13px] text-[#5A5A54]">
                @foreach ($migas as $miga)
                    @php [$texto, $url] = $miga + [null, null]; @endphp
                    <li class="flex items-center gap-1.5">
                        @unless ($loop->first)
                            <span aria-hidden="true" class="text-[#A8A8A2]">/</span>
                        @endunless
                        @if ($url)
                            <a href="{{ $url }}" wire:navigate class="text-[#44443F] underline-offset-2 hover:text-[#141413] hover:underline">{{ $texto }}</a>
                        @else
                            <span aria-current="page">{{ $texto }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
    @endif
</div>
