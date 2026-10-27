<?php

namespace Tests\Feature;

use App\Exceptions\ErrorDeStock;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\RolloAbierto;
use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Reglas de stock y corte (RN-02, RN-05, RN-07 a RN-14, RN-30, RN-35).
 *
 * Usa los dos productos de ejemplo que carga el script v1.1, cada uno con 2
 * piezas cerradas: el hexagonal HEX-12-150-50 (rollo de 50 m, se corta) y el
 * romboidal ROM-5-150-25 (no se corta).
 */
class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockService $stock;

    private Producto $hexagonal;

    private Producto $romboidal;

    private int $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = $this->app->make(StockService::class);
        $this->hexagonal = Producto::where('codigo', 'HEX-12-150-50')->firstOrFail();
        $this->romboidal = Producto::where('codigo', 'ROM-5-150-25')->firstOrFail();

        $cliente = DB::table('cliente')->insertGetId(['nombre' => 'Cliente de prueba'], 'id_cliente');
        $this->pedido = DB::table('pedido')->insertGetId(['id_cliente' => $cliente], 'id_pedido');
    }

    #[Test]
    public function un_ingreso_suma_piezas_cerradas_y_queda_registrado(): void
    {
        $movimiento = $this->stock->ingresar($this->hexagonal, 3, 'REPOSICION', 'Remito 123');

        $this->assertSame(5, $this->cerradas($this->hexagonal));
        $this->assertSame('INGRESO', $movimiento->tipo);
        $this->assertSame('REPOSICION', $movimiento->motivo);
        $this->assertEquals(3, $movimiento->cantidad);
        $this->assertSame('ROLLO', $movimiento->unidad);
    }

    #[Test]
    public function el_primer_corte_abre_un_rollo_cerrado(): void
    {
        $movimiento = $this->stock->venderMetros($this->hexagonal, 7, $this->pedido);

        $this->assertSame(1, $this->cerradas($this->hexagonal));
        $rollo = RolloAbierto::findOrFail($movimiento->id_rollo);
        $this->assertEquals(50, $rollo->metros_iniciales);
        $this->assertEquals(43, $rollo->metros_restantes);
        $this->assertEquals(-7, $movimiento->cantidad);
        $this->assertSame('METRO', $movimiento->unidad);
        $this->assertSame($this->pedido, $movimiento->id_pedido);
    }

    #[Test]
    public function el_segundo_corte_sale_del_rollo_abierto_y_no_abre_otro(): void
    {
        $primero = $this->stock->venderMetros($this->hexagonal, 7, $this->pedido);
        $segundo = $this->stock->venderMetros($this->hexagonal, 10, $this->pedido);

        $this->assertSame($primero->id_rollo, $segundo->id_rollo);
        $this->assertSame(1, $this->cerradas($this->hexagonal));
        $this->assertEquals(33, RolloAbierto::findOrFail($segundo->id_rollo)->metros_restantes);
    }

    #[Test]
    public function entre_varios_abiertos_que_alcanzan_corta_el_de_menos_metros(): void
    {
        $chico = $this->stock->venderMetros($this->hexagonal, 45, $this->pedido)->id_rollo;  // le quedan 5
        $grande = $this->stock->venderMetros($this->hexagonal, 10, $this->pedido)->id_rollo; // el chico no alcanza: abre otro, quedan 40

        $this->assertNotSame($chico, $grande);
        $this->assertSame($chico, $this->stock->venderMetros($this->hexagonal, 4, $this->pedido)->id_rollo);
        $this->assertSame($grande, $this->stock->venderMetros($this->hexagonal, 3, $this->pedido)->id_rollo);
    }

    #[Test]
    public function un_corte_no_junta_metros_de_dos_rollos(): void
    {
        $this->stock->venderMetros($this->hexagonal, 45, $this->pedido); // quedan 5
        $this->stock->venderMetros($this->hexagonal, 46, $this->pedido); // quedan 4, sin cerradas

        $this->expectException(ErrorDeStock::class);
        $this->stock->venderMetros($this->hexagonal, 7, $this->pedido); // hay 9 m, pero en dos rollos
    }

    #[Test]
    public function un_corte_no_puede_superar_el_metraje_del_rollo(): void
    {
        $this->expectException(ErrorDeStock::class);
        $this->stock->venderMetros($this->hexagonal, 60, $this->pedido);
    }

    #[Test]
    public function un_rollo_cortado_no_se_vende_como_rollo_entero(): void
    {
        $this->stock->venderMetros($this->hexagonal, 7, $this->pedido); // 1 cerrada + 43 m

        try {
            $this->stock->venderPiezas($this->hexagonal, 2, $this->pedido);
            $this->fail('Vendió dos rollos enteros con uno solo cerrado.');
        } catch (ErrorDeStock) {
            $this->assertSame(1, $this->cerradas($this->hexagonal));
        }

        $this->stock->venderPiezas($this->hexagonal, 1, $this->pedido);
        $this->assertSame(0, $this->cerradas($this->hexagonal));
    }

    #[Test]
    public function cortar_lo_que_queda_deja_el_rollo_agotado(): void
    {
        $rollo = $this->stock->venderMetros($this->hexagonal, 20, $this->pedido)->id_rollo;
        $this->stock->venderMetros($this->hexagonal, 30, $this->pedido);

        $rollo = RolloAbierto::findOrFail($rollo);
        $this->assertSame('AGOTADO', $rollo->estado);
        $this->assertEquals(0, $rollo->metros_restantes);
        $this->assertEquals(50, $this->disponible($this->hexagonal));
    }

    #[Test]
    public function lo_que_queda_bajo_tres_metros_es_retazo(): void
    {
        $rollo = $this->stock->venderMetros($this->hexagonal, 47.5, $this->pedido)->rollo;

        $this->assertEquals(2.5, $rollo->metros_restantes);
        $this->assertTrue($rollo->esRetazo());
    }

    #[Test]
    public function ejemplo_integral_de_las_reglas_de_negocio(): void
    {
        $this->stock->ingresar($this->hexagonal, 1, 'REPOSICION'); // 3 piezas = 150 m
        $this->assertEquals(150, $this->disponible($this->hexagonal));

        $this->stock->venderPiezas($this->hexagonal, 1, $this->pedido);
        $this->stock->venderMetros($this->hexagonal, 7, $this->pedido);

        // 1 pieza cerrada + 1 rollo abierto con 43 m = 93 m
        $this->assertSame(1, $this->cerradas($this->hexagonal));
        $this->assertEquals(43, RolloAbierto::abiertos()->where('id_producto', $this->hexagonal->id_producto)->value('metros_restantes'));
        $this->assertEquals(93, $this->disponible($this->hexagonal));
    }

    #[Test]
    public function un_producto_que_no_se_corta_no_se_vende_por_metro(): void
    {
        $this->expectException(ErrorDeStock::class);
        $this->stock->venderMetros($this->romboidal, 5, $this->pedido);
    }

    #[Test]
    public function si_no_alcanza_no_queda_nada_a_medias(): void
    {
        $movimientos = MovimientoStock::count();

        try {
            $this->stock->venderPiezas($this->romboidal, 3, $this->pedido);
        } catch (ErrorDeStock) {
        }

        $this->assertSame(2, $this->cerradas($this->romboidal));
        $this->assertSame($movimientos, MovimientoStock::count());
    }

    #[Test]
    public function un_ajuste_sin_motivo_no_se_guarda(): void
    {
        $this->expectException(ErrorDeStock::class);
        $this->stock->ajustarPiezas($this->romboidal, -1, '');
    }

    #[Test]
    public function solo_una_diferencia_de_inventario_suma_stock(): void
    {
        try {
            $this->stock->ajustarPiezas($this->romboidal, 1, 'OBSEQUIO');
            $this->fail('Un obsequio sumó stock.');
        } catch (ErrorDeStock) {
        }

        $this->stock->ajustarPiezas($this->romboidal, 1, 'INVENTARIO');
        $this->assertSame(3, $this->cerradas($this->romboidal));
    }

    #[Test]
    public function una_rotura_descuenta_piezas_con_su_motivo(): void
    {
        $movimiento = $this->stock->ajustarPiezas($this->romboidal, -1, 'ROTURA', 'Se mojó');

        $this->assertSame(1, $this->cerradas($this->romboidal));
        $this->assertSame('AJUSTE', $movimiento->tipo);
        $this->assertSame('ROTURA', $movimiento->motivo);
        $this->assertEquals(-1, $movimiento->cantidad);
    }

    #[Test]
    public function una_muestra_en_metros_se_corta_como_una_venta(): void
    {
        $movimiento = $this->stock->ajustarMetros($this->hexagonal, -1, 'MUESTRA');

        $this->assertSame(1, $this->cerradas($this->hexagonal));
        $this->assertEquals(49, RolloAbierto::findOrFail($movimiento->id_rollo)->metros_restantes);
        $this->assertNull($movimiento->id_pedido);
    }

    #[Test]
    public function un_ajuste_de_inventario_no_pasa_los_metros_iniciales_del_rollo(): void
    {
        $rollo = $this->stock->venderMetros($this->hexagonal, 7, $this->pedido)->rollo;

        $this->stock->ajustarMetros($this->hexagonal, 2, 'INVENTARIO', $rollo);
        $this->assertEquals(45, $rollo->refresh()->metros_restantes);

        $this->expectException(ErrorDeStock::class);
        $this->stock->ajustarMetros($this->hexagonal, 6, 'INVENTARIO', $rollo);
    }

    #[Test]
    public function anular_un_pedido_repone_piezas_y_metros_una_sola_vez(): void
    {
        $this->stock->venderPiezas($this->hexagonal, 1, $this->pedido);
        $rollo = $this->stock->venderMetros($this->hexagonal, 7, $this->pedido)->id_rollo;
        $this->assertSame(0, $this->cerradas($this->hexagonal));

        $this->stock->reponerPedido($this->pedido);
        $this->stock->reponerPedido($this->pedido);

        $this->assertSame(1, $this->cerradas($this->hexagonal));
        $this->assertEquals(50, RolloAbierto::findOrFail($rollo)->metros_restantes);
        $this->assertSame(2, MovimientoStock::where('id_pedido', $this->pedido)->where('tipo', 'ANULACION')->count());
    }

    private function cerradas(Producto $producto): int
    {
        return Stock::findOrFail($producto->id_producto)->cantidad_cerrada;
    }

    private function disponible(Producto $producto): float
    {
        return (float) DB::table('vw_stock_disponible')->where('id_producto', $producto->id_producto)->value('disponible_total');
    }
}
