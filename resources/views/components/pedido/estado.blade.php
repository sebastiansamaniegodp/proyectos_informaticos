@props(['estado'])

@php
    // El nombre del estado va siempre escrito: el color solo acompaña.
    $estilo = match ($estado) {
        'BORRADOR' => 'border-[#D5D5CF] bg-white text-[#44443F]',
        'CONFIRMADO' => 'border-[#141413] bg-[#141413] text-white',
        'ENTREGADO' => 'border-[#E8C46A] bg-[#FDF3D6] text-[#6B4E00]',
        'COBRADO' => 'border-[#2F6B45]/30 bg-[#2F6B45]/10 text-[#2F6B45]',
        default => 'border-[#B3261E]/25 bg-[#B3261E]/8 text-[#B3261E]',
    };
@endphp

<span {{ $attributes->class(['inline-flex flex-none items-center rounded-full border px-2 py-px text-[11px] leading-[18px] font-semibold whitespace-nowrap', $estilo]) }}>
    {{ \App\Models\Pedido::ESTADOS[$estado] }}
</span>
