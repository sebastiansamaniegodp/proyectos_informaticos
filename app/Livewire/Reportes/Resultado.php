<?php

namespace App\Livewire\Reportes;

use App\Models\Pedido;
use App\Services\ReporteService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pantalla "Resultado del mes" (PR-05): cuánto se vendió, cuánto costó y
 * cuánto se ganó, con los pedidos cobrados del mes. Es lo que la dueña
 * pidió para saber "cuánto gasta y cuánto gana".
 */
#[Title('Resultado del mes')]
class Resultado extends Component
{
    /** Mes que se muestra, como 2026-10. Vacío es el mes actual. */
    #[Url(except: '')]
    public string $mes = '';

    public function mount(): void
    {
        if ($this->mes !== '' && $this->fecha()->format('Y-m') !== $this->mes) {
            $this->mes = '';
        }
    }

    /** Primer día del mes elegido; nunca después del mes actual. */
    public function fecha(): CarbonImmutable
    {
        $actual = CarbonImmutable::now()->startOfMonth();

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->mes)) {
            return $actual;
        }

        $fecha = CarbonImmutable::createFromFormat('!Y-m', $this->mes);

        return $fecha->greaterThan($actual) ? $actual : $fecha;
    }

    public function anterior(): void
    {
        $this->mes = $this->fecha()->subMonth()->format('Y-m');
    }

    public function siguiente(): void
    {
        // Del mes anterior al actual se vuelve a la dirección sin ?mes=.
        $siguiente = $this->fecha()->addMonth();
        $this->mes = $siguiente->lessThan(CarbonImmutable::now()->startOfMonth()) ? $siguiente->format('Y-m') : '';
    }

    #[Computed]
    public function resultado(): object
    {
        return app(ReporteService::class)->resultadoDelMes($this->fecha());
    }

    #[Computed]
    public function familias(): Collection
    {
        return app(ReporteService::class)->ventasPorFamilia($this->fecha(), $this->fecha()->addMonth());
    }

    #[Computed]
    public function pendientes(): int
    {
        return Pedido::enEstado(Pedido::PENDIENTES)->count();
    }

    public function render(): View
    {
        return view('livewire.reportes.resultado');
    }
}
