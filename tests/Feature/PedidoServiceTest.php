<?php

namespace Tests\Feature;

use App\Exceptions\ErrorDePedido;
use App\Models\Familia;
use App\Models\MovimientoStock;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\RolloAbierto;
use App\Models\Stock;
use App\Services\PedidoService;
use App\Services\Redondeo;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pedidos: cálculo de las líneas, estados y descuento de stock (casos PED y
 * EST del plan de pruebas).
 *
 * P1 es el hexagonal HEX-12-150-50 que carga el script v1.1, llevado a la
 * situación E1 (3 piezas cerradas, 150 m). P2 es el romboidal ROM-5-150-25,
 * que no se corta. P3 es un Tellacor de 1,50 m × 25 m con el metro a 13.700.
 */
class PedidoServiceTest extends TestCase
{
    use RefreshDatabase;

    private PedidoService $pedidos;

    private StockService $stock;

    private Producto $p1;

    private Producto $p2;

    private Producto $p3;

    private int $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pedidos = $this->app->make(PedidoService::class);
        $this->stock = $this->app->make(StockService::class);

        $this->p1 = Producto::where('codigo', 'HEX-12-150-50')->firstOrFail();
        $this->p2 = Producto::where('codigo', 'ROM-5-150-25')->firstOrFail();
        $this->p3 = Producto::create([
            'codigo' => 'TEL-150-25',
            'descripcion' => 'Tejido Tellacor de 1,50 m x 25 m',
            'id_familia' => Familia::where('nombre', 'Tejido Tellacor')->value('id_familia'),
            'unidad_venta' => 'ROLLO',
            'fraccionable' => 'S',
            'metraje_rollo' => 25,
            'altura_cm' => 150,
            'precio_venta' => 310000,
            'precio_metro' => 13700,
            'costo' => 232500,
        ]);

        // E1: 3 piezas cerradas de P1 (el script carga 2).
        $this->stock->ingresar($this->p1, 1, 'REPOSICION');
        $this->stock->ingresar($this->p3, 4, 'CARGA_INICIAL');

        $this->cliente = DB::table('cliente')->insertGetId(['nombre' => 'Cliente Prueba Uno'], 'id_cliente');
    }

    private function rollo(Producto $producto, int $cantidad = 1, ?int $precio = null): array
    {
        return [
            'id_producto' => $producto->id_producto,
            'tipo_venta' => $producto->unidad_venta,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio ?? $producto->precio_venta,
            'descuento_pct' => 0,
        ];
    }

    private function metros(Producto $producto, float|string $metros, float|string $descuento = 0): array
    {
        return [
            'id_producto' => $producto->id_producto,
            'tipo_venta' => 'METRO',
            'cantidad' => $metros,
            'precio_unitario' => $producto->precio_metro,
            'descuento_pct' => $descuento,
        ];
    }

    /** El pedido del ejemplo integral: 1 rollo de P1 + 7 m con 5%, flete 100.000 (PED-07). */
    private function ejemploIntegral(): Pedido
    {
        return $this->pedidos->guardar(null, $this->cliente, [
            $this->rollo($this->p1),
            $this->metros($this->p1, 7, 5),
        ], 100000);
    }

    private function cerradas(Producto $producto): int
    {
        return Stock::findOrFail($producto->id_producto)->cantidad_cerrada;
    }

    private function disponible(Producto $producto): float
    {
        return (float) DB::table('vw_stock_disponible')->where('id_producto', $producto->id_producto)->value('disponible_total');
    }

    #[Test]
    public function el_importe_se_redondea_al_final_y_el_empate_va_hacia_arriba(): void
    {
        $this->assertSame(1134000, Redondeo::importeLinea(1, 1134000));   // PED-01
        $this->assertSame(175000, Redondeo::importeLinea(7, 25000));      // PED-02
        $this->assertSame(166500, Redondeo::importeLinea(7, 25000, 5));   // PED-03: 166.250 → 166.500
        $this->assertSame(40500, Redondeo::importeLinea(3, 13700, 2));    // PED-04: 40.278 → 40.500
        $this->assertSame(41000, Redondeo::importeLinea(3, 13700));       // PED-05: 41.100 → 41.000
        $this->assertSame(13500, Redondeo::importeLinea(1, 13700, 3));    // PED-13: 13.289 → 13.500
        $this->assertSame(40000, Redondeo::importeLinea(2, 25000, 20));   // PED-15: retazo
        $this->assertSame(187500, Redondeo::importeLinea('7,5', 25000));  // coma decimal de la pantalla
    }

    #[Test]
    public function el_ejemplo_integral_da_los_totales_del_documento_de_reglas(): void
    {
        $pedido = $this->ejemploIntegral();

        $this->assertSame('BORRADOR', $pedido->estado);
        $this->assertSame(1300500, $pedido->subtotal);
        $this->assertSame(100000, $pedido->monto_flete);
        $this->assertSame(1400500, $pedido->total);
        $this->assertSame([1134000, 166500], $pedido->lineas->pluck('subtotal')->all());
    }

    #[Test]
    public function un_borrador_no_descuenta_stock(): void
    {
        $this->ejemploIntegral();

        $this->assertSame(3, $this->cerradas($this->p1));
        $this->assertSame(0, MovimientoStock::where('tipo', 'VENTA')->count());
    }

    #[Test]
    public function el_descuento_manual_no_pasa_del_cinco_por_ciento(): void
    {
        $this->pedidos->guardar(null, $this->cliente, [$this->metros($this->p1, 7, 5)], 0);

        foreach (['5.1', '-1', '10'] as $descuento) {
            try {
                $this->pedidos->guardar(null, $this->cliente, [$this->metros($this->p1, 7, $descuento)], 0);
                $this->fail("Aceptó un descuento de {$descuento}%.");
            } catch (ErrorDePedido) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function el_retazo_lleva_veinte_por_ciento_solo_en_un_corte(): void
    {
        $pedido = $this->pedidos->guardar(null, $this->cliente, [$this->metros($this->p1, 2, 20)], 0);
        $this->assertSame(40000, $pedido->subtotal);

        $this->expectException(ErrorDePedido::class);
        $this->pedidos->guardar(null, $this->cliente, [array_merge($this->rollo($this->p1), ['descuento_pct' => 20])], 0);
    }

    #[Test]
    public function un_producto_que_no_se_corta_no_se_vende_por_metro(): void
    {
        $this->expectException(ErrorDePedido::class);
        $this->expectExceptionMessage('no se corta');

        $this->pedidos->guardar(null, $this->cliente, [[
            'id_producto' => $this->p2->id_producto,
            'tipo_venta' => 'METRO',
            'cantidad' => 5,
            'precio_unitario' => $this->p2->precio_venta,
        ]], 0);
    }

    #[Test]
    public function la_cantidad_tiene_que_ser_mayor_a_cero(): void
    {
        foreach ([0, -2] as $cantidad) {
            try {
                $this->pedidos->guardar(null, $this->cliente, [$this->rollo($this->p1, $cantidad)], 0);
                $this->fail("Aceptó la cantidad {$cantidad}.");
            } catch (ErrorDePedido) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function el_flete_no_puede_ser_negativo(): void
    {
        $this->expectException(ErrorDePedido::class);

        $this->pedidos->guardar(null, $this->cliente, [$this->rollo($this->p1)], -10000);
    }

    #[Test]
    public function el_mayorista_se_elige_a_mano_y_no_hay_escala_por_cantidad(): void
    {
        $lista = $this->pedidos->guardar(null, $this->cliente, [$this->rollo($this->p1, 10)], 0);
        $mayorista = $this->pedidos->guardar(null, $this->cliente, [$this->rollo($this->p1, 10, 1080000)], 0);

        $this->assertSame(11340000, $lista->subtotal);
        $this->assertSame(10800000, $mayorista->subtotal);
    }

    #[Test]
    public function un_precio_que_no_es_del_producto_se_rechaza(): void
    {
        $this->expectException(ErrorDePedido::class);

        $this->pedidos->guardar(null, $this->cliente, [$this->rollo($this->p1, 1, 1000)], 0);
    }

    #[Test]
    public function el_pedido_guardado_conserva_el_precio_aunque_el_producto_cambie(): void
    {
        $pedido = $this->pedidos->guardar(null, $this->cliente, [$this->rollo($this->p1)], 0);

        $this->p1->update(['precio_venta' => 1200000]);

        // Se vuelve a guardar con el precio que ya tenía la línea (PED-11).
        $pedido = $this->pedidos->guardar($pedido, $this->cliente, [$this->rollo($this->p1, 1, 1134000)], 0);
        $nuevo = $this->pedidos->guardar(null, $this->cliente, [$this->rollo($this->p1->refresh())], 0);

        $this->assertSame(1134000, $pedido->lineas->first()->precio_unitario);
        $this->assertSame(1200000, $nuevo->lineas->first()->precio_unitario);
    }

    #[Test]
    public function confirmar_descuenta_el_stock_del_ejemplo_integral(): void
    {
        $pedido = $this->pedidos->confirmar($this->ejemploIntegral());

        $this->assertSame('CONFIRMADO', $pedido->estado);
        $this->assertNotNull($pedido->fecha_confirmacion);
        $this->assertSame(1, $this->cerradas($this->p1));
        $this->assertEquals(93, $this->disponible($this->p1));

        $corte = $pedido->lineas[1];
        $this->assertNotNull($corte->id_rollo);
        $this->assertEquals(43, RolloAbierto::findOrFail($corte->id_rollo)->metros_restantes);

        // El costo se copia a la línea al confirmar (RN-34): 850.500 la pieza y 17.010 el metro.
        $this->assertSame([850500, 17010], $pedido->lineas->pluck('costo_unitario')->all());
        $this->assertSame(969570, $pedido->lineas->sum(fn ($linea) => $linea->costo()));
    }

    #[Test]
    public function un_pedido_confirmado_no_se_puede_cambiar(): void
    {
        $pedido = $this->pedidos->confirmar($this->ejemploIntegral());

        $this->expectException(ErrorDePedido::class);
        $this->pedidos->guardar($pedido, $this->cliente, [$this->rollo($this->p1, 2)], 100000);
    }

    #[Test]
    public function confirmar_dos_veces_no_descuenta_dos_veces(): void
    {
        $pedido = $this->ejemploIntegral();
        $this->pedidos->confirmar($pedido);

        try {
            $this->pedidos->confirmar($pedido);
            $this->fail('Confirmó dos veces el mismo pedido.');
        } catch (ErrorDePedido) {
            $this->assertSame(1, $this->cerradas($this->p1));
        }
    }

    #[Test]
    public function si_una_linea_no_tiene_stock_no_se_descuenta_nada(): void
    {
        // La primera línea alcanza, la segunda no: el stock queda como estaba (RN-28, SP-04).
        $pedido = $this->pedidos->guardar(null, $this->cliente, [
            $this->rollo($this->p1),
            $this->rollo($this->p1, 3),
        ], 0);

        try {
            $this->pedidos->confirmar($pedido);
            $this->fail('Confirmó un pedido sin stock.');
        } catch (ErrorDePedido $error) {
            $this->assertStringContainsString('línea 2', $error->getMessage());
        }

        $this->assertSame('BORRADOR', $pedido->refresh()->estado);
        $this->assertSame(3, $this->cerradas($this->p1));
        $this->assertSame(0, MovimientoStock::where('tipo', 'VENTA')->count());
    }

    #[Test]
    public function un_borrador_no_reserva_la_ultima_pieza(): void
    {
        // E8: una sola pieza cerrada (PED-16).
        $this->stock->ajustarPiezas($this->p1, -2, 'INVENTARIO');
        $a = $this->pedidos->guardar(null, $this->cliente, [$this->rollo($this->p1)], 0);
        $b = $this->pedidos->guardar(null, $this->cliente, [$this->rollo($this->p1)], 0);

        $this->pedidos->confirmar($b);
        $this->assertSame(0, $this->cerradas($this->p1));

        $this->expectException(ErrorDePedido::class);
        $this->pedidos->confirmar($a);
    }

    #[Test]
    public function anular_un_borrador_no_toca_el_stock(): void
    {
        $pedido = $this->pedidos->anular($this->ejemploIntegral());

        $this->assertSame('ANULADO', $pedido->estado);
        $this->assertEquals(150, $this->disponible($this->p1));
        $this->assertSame(0, MovimientoStock::whereNotNull('id_pedido')->count());
    }

    #[Test]
    public function anular_un_confirmado_repone_el_stock(): void
    {
        $pedido = $this->pedidos->confirmar($this->ejemploIntegral());
        $idRollo = $pedido->lineas[1]->id_rollo;

        $this->pedidos->anular($pedido);

        // EST-03: la pieza vuelve al contador y los 7 m a su rollo (SP-05).
        $this->assertSame(2, $this->cerradas($this->p1));
        $this->assertEquals(50, RolloAbierto::findOrFail($idRollo)->metros_restantes);
        $this->assertEquals(150, $this->disponible($this->p1));
        $this->assertSame(2, MovimientoStock::where('id_pedido', $pedido->id_pedido)->where('tipo', 'ANULACION')->count());
    }

    #[Test]
    public function el_pedido_sigue_el_circuito_hasta_el_cobro(): void
    {
        $pedido = $this->pedidos->confirmar($this->ejemploIntegral());
        $pedido = $this->pedidos->marcarEntregado($pedido);

        $this->assertSame('ENTREGADO', $pedido->estado);
        $this->assertNotNull($pedido->fecha_entrega);
        $this->assertSame(1, $this->cerradas($this->p1));

        $pedido = $this->pedidos->registrarCobro($pedido);

        $this->assertSame('COBRADO', $pedido->estado);
        $this->assertNotNull($pedido->fecha_cobro);
    }

    #[Test]
    public function un_pedido_no_saltea_estados(): void
    {
        $borrador = $this->ejemploIntegral();
        $confirmado = $this->pedidos->confirmar($this->pedidos->guardar(null, $this->cliente, [$this->rollo($this->p3)], 0));

        // EST-09: BORRADOR → ENTREGADO, BORRADOR → COBRADO y CONFIRMADO → COBRADO.
        foreach ([
            fn () => $this->pedidos->marcarEntregado($borrador),
            fn () => $this->pedidos->registrarCobro($borrador),
            fn () => $this->pedidos->registrarCobro($confirmado),
        ] as $transicion) {
            try {
                $transicion();
                $this->fail('Aceptó una transición que no sigue.');
            } catch (ErrorDePedido) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function cobrado_y_anulado_son_estados_finales(): void
    {
        $cobrado = $this->pedidos->registrarCobro($this->pedidos->marcarEntregado($this->pedidos->confirmar($this->ejemploIntegral())));
        $anulado = $this->pedidos->anular($this->pedidos->guardar(null, $this->cliente, [$this->rollo($this->p3)], 0));

        foreach ([
            fn () => $this->pedidos->anular($cobrado),
            fn () => $this->pedidos->guardar($cobrado, $this->cliente, [$this->rollo($this->p1)], 0),
            fn () => $this->pedidos->confirmar($anulado),
            fn () => $this->pedidos->guardar($anulado, $this->cliente, [$this->rollo($this->p3)], 0),
        ] as $operacion) {
            try {
                $operacion();
                $this->fail('Cambió un pedido en estado final.');
            } catch (ErrorDePedido) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function el_resumen_junta_las_lineas_del_mismo_producto(): void
    {
        $pedido = $this->ejemploIntegral()->load('lineas.producto.familia');

        $this->assertSame('1 rollo + 7 m Hexagonal 1,50', $pedido->resumen());
    }
}
