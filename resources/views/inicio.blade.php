{{-- Provisorio: la pantalla de Inicio del prototipo llega con su propia tarea. --}}
<x-layouts::app title="Inicio">
    <div class="max-w-[1200px] px-4 py-4 lg:px-8 lg:py-6">
        <x-encabezado titulo="Inicio" />

        <div class="rounded-[10px] border border-[#E3E3DE] bg-white px-4 py-3.5 lg:px-5 lg:py-4">
            <div class="text-base font-semibold">Hola, {{ auth()->user()->nombre }}</div>
            <div class="mt-1 text-sm leading-normal text-[#5A5A54]">Entrá a cada módulo desde el menú de la izquierda.</div>
        </div>
    </div>
</x-layouts::app>
