<?php

namespace App\Livewire;

use App\Services\ReporteService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pantalla de Inicio: lo que la dueña mira cada mañana. Los pedidos por
 * confirmar y por cobrar, cómo va el mes, las ventas de los últimos meses
 * partidas en costo y ganancia, y los rollos abiertos.
 *
 * Las cifras de ventas y ganancia salen de los pedidos cobrados, igual que
 * el resultado del mes, así los dos números coinciden siempre.
 */
#[Title('Inicio')]
class Inicio extends Component
{
    /** Meses de las gráficas, contando el actual. */
    public const MESES = 6;

    private function reportes(): ReporteService
    {
        return app(ReporteService::class);
    }

    #[Computed]
    public function mes(): object
    {
        return $this->reportes()->resultadoDelMes(CarbonImmutable::now());
    }

    /**
     * Lo vendido en el mismo tramo del mes anterior (del 1 al mismo día),
     * para comparar un mes que todavía no terminó con algo parejo.
     */
    #[Computed]
    public function comparacion(): ?object
    {
        $hoy = CarbonImmutable::now();
        $desde = $hoy->subMonthNoOverflow()->startOfMonth();
        $hasta = $desde->setDay(min($hoy->day, $desde->daysInMonth))->addDay()->startOfDay();

        $anterior = $this->reportes()->ventasPorFamilia($desde, $hasta)->sum('ventas');

        if ($anterior === 0) {
            return null;
        }

        return (object) [
            'cambio' => ($this->mes->ventas - $anterior) / $anterior * 100,
            'tramo' => '1 al '.$hasta->subDay()->day.' de '.$desde->locale('es')->translatedFormat('F'),
        ];
    }

    #[Computed]
    public function abiertos(): Collection
    {
        return $this->reportes()->pedidosAbiertos();
    }

    #[Computed]
    public function pendientes(): Collection
    {
        return $this->reportes()->pedidosPendientes(6);
    }

    #[Computed]
    public function porCobrar(): Collection
    {
        return $this->reportes()->porCobrar(5);
    }

    #[Computed]
    public function meses(): Collection
    {
        return $this->reportes()->resultadoPorMes(CarbonImmutable::now(), self::MESES);
    }

    #[Computed]
    public function familias(): Collection
    {
        $hasta = CarbonImmutable::now()->startOfMonth()->addMonth();

        return $this->reportes()->ventasPorFamilia($hasta->subMonths(self::MESES), $hasta);
    }

    #[Computed]
    public function masVendidos(): Collection
    {
        $hasta = CarbonImmutable::now()->startOfMonth()->addMonth();

        return $this->reportes()->masVendidos($hasta->subMonths(self::MESES), $hasta, 5);
    }

    #[Computed]
    public function rollos(): Collection
    {
        return $this->reportes()->rollosAbiertos(6);
    }

    public function render(): View
    {
        return view('livewire.inicio');
    }
}
