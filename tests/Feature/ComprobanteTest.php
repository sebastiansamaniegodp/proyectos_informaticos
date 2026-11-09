<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\ComprobanteService;
use App\Services\PedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Comprobante del pedido en PDF (casos COMP del plan de pruebas).
 */
class ComprobanteTest extends TestCase
{
    use RefreshDatabase;

    private PedidoService $pedidos;

    private Producto $p1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pedidos = $this->app->make(PedidoService::class);
        $this->p1 = Producto::where('codigo', 'HEX-12-150-50')->firstOrFail();
        $this->actingAs(Usuario::create(['username' => 'prueba', 'clave_hash' => 'secreta', 'nombre' => 'Prueba', 'activo' => 'S']));
    }

    /** El ejemplo integral (PED-07) para un cliente que solo tiene el nombre (C1). */
    private function pedido(): Pedido
    {
        $cliente = Cliente::create(['nombre' => 'Cliente Prueba Uno', 'activo' => 'S']);

        return $this->pedidos->guardar(null, $cliente->id_cliente, [
            ['id_producto' => $this->p1->id_producto, 'tipo_venta' => 'ROLLO', 'cantidad' => 1, 'precio_unitario' => 1134000],
            ['id_producto' => $this->p1->id_producto, 'tipo_venta' => 'METRO', 'cantidad' => 7, 'precio_unitario' => 25000, 'descuento_pct' => 5],
        ], 100000);
    }

    #[Test]
    public function un_pedido_confirmado_tiene_comprobante_en_pdf(): void
    {
        $pedido = $this->pedidos->confirmar($this->pedido());

        $respuesta = $this->get(route('pedidos.comprobante', $pedido));

        // COMP-01
        $respuesta->assertOk();
        $respuesta->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    #[Test]
    public function el_comprobante_lleva_lineas_totales_y_la_leyenda_fiscal(): void
    {
        $pedido = $this->pedidos->confirmar($this->pedido())->load(['cliente', 'lineas.producto.familia']);

        $html = view('pdf.comprobante', ['pedido' => $pedido, 'logo' => null, 'negocio' => config('negocio')])->render();

        // COMP-01 y COMP-03: el cliente solo tiene nombre, y no aparece "null" en ningún lado.
        $this->assertStringContainsString('Cliente Prueba Uno', $html);
        $this->assertStringContainsString('1.134.000', $html);
        $this->assertStringContainsString('166.500', $html);
        $this->assertStringContainsString('1.300.500', $html);
        $this->assertStringContainsString('100.000', $html);
        $this->assertStringContainsString('1.400.500', $html);
        $this->assertStringContainsString('sin validez fiscal', $html);
        $this->assertStringNotContainsStringIgnoringCase('null', $html);
        $this->assertStringNotContainsString('Documento:', $html);
    }

    #[Test]
    public function un_borrador_no_tiene_comprobante(): void
    {
        $pedido = $this->pedido();

        // COMP-04 (SP-09)
        $this->get(route('pedidos.comprobante', $pedido))->assertNotFound();
    }

    #[Test]
    public function un_pedido_anulado_no_tiene_comprobante(): void
    {
        $pedido = $this->pedidos->anular($this->pedidos->confirmar($this->pedido()));

        $this->get(route('pedidos.comprobante', $pedido))->assertNotFound();
    }

    #[Test]
    public function el_nombre_del_archivo_no_lleva_tildes_ni_espacios(): void
    {
        $cliente = Cliente::create(['nombre' => 'Ramón Núñez', 'activo' => 'S']);
        $pedido = new Pedido;
        $pedido->id_pedido = 124;
        $pedido->setRelation('cliente', $cliente);

        $this->assertSame('Pedido-00124-Ramon-Nunez.pdf', $this->app->make(ComprobanteService::class)->nombreArchivo($pedido));
    }
}
