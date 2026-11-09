<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Producto;
use App\Services\PedidoService;
use App\Services\ReporteService;
use App\Services\StockService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Resultado del mes y pedidos pendientes (casos REP del plan de pruebas).
 * P1 es el hexagonal HEX-12-150-50 del script v1.1: rollo a 1.134.000,
 * metro a 25.000 y costo 850.500 (17.010 el metro).
 */
class ReporteServiceTest extends TestCase
{
    use RefreshDatabase;

    private PedidoService $pedidos;

    private ReporteService $reportes;

    private Producto $p1;

    private int $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pedidos = $this->app->make(PedidoService::class);
        $this->reportes = $this->app->make(ReporteService::class);
        $this->p1 = Producto::where('codigo', 'HEX-12-150-50')->firstOrFail();
        $this->app->make(StockService::class)->ingresar($this->p1, 8, 'REPOSICION');

        $this->cliente = DB::table('cliente')->insertGetId(['nombre' => 'Nicolasa Benítez'], 'id_cliente');
    }

    /** Pedido del ejemplo integral (PED-07), confirmado. */
    private function ejemploIntegral(): Pedido
    {
        $pedido = $this->pedidos->guardar(null, $this->cliente, [
            ['id_producto' => $this->p1->id_producto, 'tipo_venta' => 'ROLLO', 'cantidad' => 1, 'precio_unitario' => 1134000],
            ['id_producto' => $this->p1->id_producto, 'tipo_venta' => 'METRO', 'cantidad' => 7, 'precio_unitario' => 25000, 'descuento_pct' => 5],
        ], 100000);

        return $this->pedidos->confirmar($pedido);
    }

    private function cobrar(Pedido $pedido, string $fecha): Pedido
    {
        $this->travelTo(CarbonImmutable::parse($fecha));
        $pedido = $this->pedidos->registrarCobro($this->pedidos->marcarEntregado($pedido));
        $this->travelBack();

        return $pedido;
    }

    #[Test]
    public function el_resultado_del_mes_da_las_cifras_del_ejemplo_integral(): void
    {
        $this->cobrar($this->ejemploIntegral(), '2026-10-14 10:00');

        $octubre = $this->reportes->resultadoDelMes(CarbonImmutable::parse('2026-10-01'));

        // REP-01
        $this->assertSame(1300500, $octubre->ventas);
        $this->assertSame(969570, $octubre->costo);
        $this->assertSame(330930, $octubre->ganancia);
        $this->assertSame(100000, $octubre->flete);
        $this->assertCount(1, $octubre->pedidos);
    }

    #[Test]
    public function los_pedidos_sin_cobrar_y_los_anulados_no_suman(): void
    {
        $confirmado = $this->ejemploIntegral();
        $this->pedidos->marcarEntregado($this->ejemploIntegral());
        $this->pedidos->anular($this->ejemploIntegral());

        $mes = $this->reportes->resultadoDelMes(now());

        // REP-02 y REP-03
        $this->assertSame(0, $mes->ventas);
        $this->assertCount(0, $mes->pedidos);
        $this->assertSame('CONFIRMADO', $confirmado->estado);
    }

    #[Test]
    public function un_pedido_cuenta_en_el_mes_en_que_se_cobro(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-30 09:00'));
        $pedido = $this->ejemploIntegral();
        $this->travelBack();

        $this->cobrar($pedido, '2026-11-02 11:00');

        // REP-04
        $this->assertSame(0, $this->reportes->resultadoDelMes(CarbonImmutable::parse('2026-10-01'))->ventas);
        $this->assertSame(1300500, $this->reportes->resultadoDelMes(CarbonImmutable::parse('2026-11-01'))->ventas);
    }

    #[Test]
    public function cambiar_el_costo_del_producto_no_cambia_un_mes_cerrado(): void
    {
        $this->cobrar($this->ejemploIntegral(), '2026-10-14 10:00');

        $this->p1->update(['costo' => 900000]);

        // REP-05
        $this->assertSame(969570, $this->reportes->resultadoDelMes(CarbonImmutable::parse('2026-10-01'))->costo);
    }

    #[Test]
    public function el_costo_de_un_corte_es_el_del_metro_y_no_el_de_la_pieza(): void
    {
        $pedido = $this->pedidos->confirmar($this->pedidos->guardar(null, $this->cliente, [
            ['id_producto' => $this->p1->id_producto, 'tipo_venta' => 'METRO', 'cantidad' => 10, 'precio_unitario' => 25000],
        ], 0));
        $this->cobrar($pedido, '2026-10-20 10:00');

        $octubre = $this->reportes->resultadoDelMes(CarbonImmutable::parse('2026-10-01'));

        // REP-06: 10 × 17.010, no 10 × 850.500.
        $this->assertSame(250000, $octubre->ventas);
        $this->assertSame(170100, $octubre->costo);
        $this->assertSame(79900, $octubre->ganancia);
    }

    #[Test]
    public function los_pendientes_son_los_borradores_y_los_confirmados(): void
    {
        $borrador = $this->pedidos->guardar(null, $this->cliente, [
            ['id_producto' => $this->p1->id_producto, 'tipo_venta' => 'ROLLO', 'cantidad' => 1, 'precio_unitario' => 1134000],
        ], 0);
        $confirmado = $this->ejemploIntegral();
        $this->pedidos->marcarEntregado($this->ejemploIntegral());
        $this->cobrar($this->ejemploIntegral(), '2026-11-05 10:00');
        $this->pedidos->anular($this->ejemploIntegral());

        // REP-07
        $this->assertEqualsCanonicalizing(
            [$borrador->id_pedido, $confirmado->id_pedido],
            $this->reportes->pedidosPendientes()->pluck('id_pedido')->all(),
        );
        $this->assertSame(1, $this->reportes->pedidosAbiertos()['ENTREGADO']->cantidad);
    }

    #[Test]
    public function el_resultado_por_mes_trae_los_meses_sin_cobros_en_cero(): void
    {
        $this->cobrar($this->ejemploIntegral(), '2026-09-10 10:00');
        $this->cobrar($this->ejemploIntegral(), '2026-11-03 10:00');

        $meses = $this->reportes->resultadoPorMes(CarbonImmutable::parse('2026-11-09'), 3);

        $this->assertSame(['2026-09', '2026-10', '2026-11'], $meses->map(fn ($mes) => $mes->mes->format('Y-m'))->all());
        $this->assertSame([1300500, 0, 1300500], $meses->pluck('ventas')->all());
        $this->assertSame([330930, 0, 330930], $meses->pluck('ganancia')->all());
    }

    #[Test]
    public function las_ventas_por_familia_suman_las_lineas(): void
    {
        $this->cobrar($this->ejemploIntegral(), '2026-11-03 10:00');

        $familias = $this->reportes->ventasPorFamilia(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-12-01'));

        $this->assertCount(1, $familias);
        $this->assertSame('Hexagonal', $familias[0]->nombre_corto);
        $this->assertSame(1300500, $familias[0]->ventas);
        $this->assertSame(330930, $familias[0]->ganancia);
    }
}
