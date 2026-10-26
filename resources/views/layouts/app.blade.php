@php
    use Illuminate\Support\Str;

    // Módulos del menú lateral. Los que todavía no tienen pantalla van con
    // 'ruta' => null y se muestran como "Pronto"; cada módulo pone su ruta
    // acá cuando se hace su tarea.
    $modulos = [
        ['nombre' => 'Inicio', 'ruta' => 'inicio', 'activa' => 'inicio', 'icono' => '<path d="M3 10.5 12 3l9 7.5V21H3z"/>'],
        ['nombre' => 'Pedidos', 'ruta' => null, 'activa' => 'pedidos.*', 'icono' => '<path d="M5 4h14v16H5z"/><path d="M9 9h6M9 13h6"/>'],
        ['nombre' => 'Clientes', 'ruta' => null, 'activa' => 'clientes.*', 'icono' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.6 3.4-5.5 6.5-5.5s5.7 1.9 6.5 5.5M16 4.8a3.5 3.5 0 0 1 0 6.4M18 14.8c1.8.7 3 2.4 3.5 5.2"/>'],
        ['nombre' => 'Stock', 'ruta' => null, 'activa' => 'stock.*', 'icono' => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/>'],
        ['nombre' => 'Catálogo', 'ruta' => 'productos.catalogo', 'activa' => 'productos.*', 'icono' => '<path d="M4 7h16M4 12h16M4 17h16"/>'],
        ['nombre' => 'Resultado del mes', 'ruta' => null, 'activa' => 'reportes.*', 'icono' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'],
    ];

    $usuario = auth()->user();
    $iniciales = Str::of($usuario?->nombre ?? '')->explode(' ')->filter()->take(2)
        ->map(fn ($palabra) => Str::upper(Str::substr($palabra, 0, 1)))->implode('');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141413">
    <title>{{ $title ?? 'Shopping de los Tejidos' }} · Shopping de los Tejidos</title>
    {{-- La barra lateral achicada se recuerda en el navegador. Se aplica antes
         de dibujar la página para que no se vea el salto de ancho. --}}
    <script>try { if (localStorage.getItem('menu-mini') === '1') document.documentElement.classList.add('menu-mini'); } catch (e) {}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="m-0 bg-[#F4F4F1] font-sans text-[#141413]">
<div x-data="menuLateral" x-on:keydown.escape.window="abierto = false" class="min-h-dvh lg:flex">

    {{-- Fondo oscuro detrás del menú abierto en el celular. --}}
    <div x-show="abierto" x-cloak x-transition.opacity x-on:click="abierto = false"
        class="fixed inset-0 z-30 bg-black/45 lg:hidden"></div>

    <aside id="menu-lateral" aria-label="Menú principal"
        x-bind:class="abierto && 'max-lg:translate-x-0 max-lg:shadow-2xl'"
        class="fixed inset-y-0 left-0 z-40 flex w-[250px] -translate-x-full flex-col bg-[#141413] text-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-dvh lg:flex-none lg:translate-x-0 lg:transition-[width] lg:mini:w-[72px]">

        <a href="{{ route('inicio') }}" wire:navigate class="flex h-14 flex-none items-center border-b border-white/10 px-4 lg:mini:justify-center lg:mini:px-2">
            <img src="{{ asset('img/logo.png') }}" alt="Shopping de los Tejidos" class="h-8 w-auto lg:mini:h-5">
        </a>

        <div class="flex flex-none items-center gap-3 border-b border-white/10 px-4 py-3 lg:mini:justify-center lg:mini:px-2">
            <span class="flex size-9 flex-none items-center justify-center rounded-full bg-[#2C2C29] text-sm font-semibold text-white" aria-hidden="true">{{ $iniciales }}</span>
            <span class="min-w-0 truncate text-sm text-[#C9C9C3] lg:mini:hidden">{{ $usuario?->nombre }}</span>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4">
            <div class="px-3 pb-2 text-[11px] font-semibold tracking-[0.08em] text-[#8A8A84] uppercase lg:mini:sr-only">Módulos</div>
            <ul class="m-0 flex list-none flex-col gap-0.5 p-0">
                @foreach ($modulos as $modulo)
                    @php
                        $activa = request()->routeIs($modulo['activa']);
                        $icono = '<svg class="flex-none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$modulo['icono'].'</svg>';
                        $item = 'flex min-h-11 items-center gap-3 rounded-lg px-3 text-[15px] no-underline lg:mini:justify-center lg:mini:px-0';
                    @endphp
                    <li>
                        @if ($modulo['ruta'])
                            <a href="{{ route($modulo['ruta']) }}" title="{{ $modulo['nombre'] }}" wire:navigate
                                @if ($activa) aria-current="page" @endif
                                @class([
                                    $item,
                                    'bg-[#2C2C29] font-semibold text-white' => $activa,
                                    'font-medium text-[#C9C9C3] hover:bg-white/5 hover:text-white' => ! $activa,
                                ])>
                                {!! $icono !!}
                                <span class="truncate lg:mini:sr-only">{{ $modulo['nombre'] }}</span>
                            </a>
                        @else
                            <span aria-disabled="true" title="{{ $modulo['nombre'] }} (pronto)" class="{{ $item }} cursor-default font-medium text-[#6B6B66]">
                                {!! $icono !!}
                                <span class="truncate lg:mini:sr-only">{{ $modulo['nombre'] }}</span>
                                <span class="ml-auto flex-none rounded-full border border-white/15 px-2 py-px text-[11px] lg:mini:hidden">Pronto</span>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="flex-none border-t border-white/10 p-3">
            @csrf
            <button type="submit" title="Salir"
                class="flex min-h-11 w-full cursor-pointer items-center gap-3 rounded-lg border-0 bg-transparent px-3 text-[15px] font-medium text-[#C9C9C3] hover:bg-white/5 hover:text-white lg:mini:justify-center lg:mini:px-0">
                <svg class="flex-none" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11"/></svg>
                <span class="lg:mini:sr-only">Salir</span>
            </button>
        </form>
    </aside>

    <div class="flex min-h-dvh min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-14 flex-none items-center gap-2 border-b border-[#E3E3DE] bg-white px-2 lg:px-4">
            <button type="button" x-on:click="alternar()" aria-controls="menu-lateral" x-bind:aria-expanded="abierto"
                aria-label="Mostrar u ocultar el menú"
                class="flex size-11 cursor-pointer items-center justify-center rounded-lg border-0 bg-transparent text-[#141413] hover:bg-[#F4F4F1]">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span class="font-titulo text-base font-semibold lg:hidden">Shopping de los Tejidos</span>
        </header>

        <main id="contenido" class="flex flex-1 flex-col">
            {{ $slot }}
        </main>
    </div>

</div>
@livewireScripts
</body>
</html>
