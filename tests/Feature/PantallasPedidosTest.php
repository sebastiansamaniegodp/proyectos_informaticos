<?php

namespace Tests\Feature;

use App\Livewire\Pedidos\Detalle;
use App\Livewire\Pedidos\Formulario;
use App\Livewire\Pedidos\Listado;
use App\Livewire\Reportes\Resultado;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\PedidoService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Las pantallas de pedidos, Inicio y Resultado del mes, de punta a punta.
 */
class PantallasPedidosTest extends TestCase
{
    use RefreshDatabase;

    private Producto $p1;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(Usuario::create(['username' => 'prueba', 'clave_hash' => 'secreta', 'nombre' => 'Prueba', 'rol' => 'ADMIN', 'activo' => 'S']));
        $this->p1 = Producto::where('codigo', 'HEX-12-150-50')->firstOrFail();
        $this->app->make(StockService::class)->ingresar($this->p1, 1, 'REPOSICION');
        $this->cliente = Cliente::create(['nombre' => 'Nicolasa Benítez', 'telefono' => '0981 234 567', 'activo' => 'S']);
    }

    private function pedidoConfirmado(): Pedido
    {
        $pedidos = $this->app->make(PedidoService::class);

        return $pedidos->confirmar($pedidos->guardar(null, $this->cliente->id_cliente, [
            ['id_producto' => $this->p1->id_producto, 'tipo_venta' => 'ROLLO', 'cantidad' => 1, 'precio_unitario' => 1134000],
        ], 0));
    }

    #[Test]
    public function el_ejemplo_integral_se_carga_y_confirma_desde_la_pantalla(): void
    {
        $pantalla = Livewire::test(Formulario::class)
            ->call('elegirCliente', $this->cliente->id_cliente)
            // Línea 1: un rollo entero.
            ->call('abrirLinea')
            ->set('busquedaProducto', 'hexagonal')
            ->call('elegirProducto', $this->p1->id_producto)
            ->set('cantidad', '1')
            ->call('guardarLinea')
            ->assertHasNoErrors()
            // Línea 2: 7 m con 5% de descuento.
            ->call('abrirLinea')
            ->call('elegirProducto', $this->p1->id_producto)
            ->set('forma', 'METRO')
            ->set('cantidad', '7')
            ->set('descuento', '5')
            ->assertSet('panelLinea', true);

        $this->assertSame(166500, $pantalla->instance()->calculo['subtotal']);

        $pantalla->call('guardarLinea')
            ->assertHasNoErrors()
            ->set('flete', '100.000')
            ->call('confirmar')
            ->assertHasNoErrors()
            ->assertRedirect();

        $pedido = Pedido::firstOrFail();
        $this->assertSame('CONFIRMADO', $pedido->estado);
        $this->assertSame(1300500, $pedido->subtotal);
        $this->assertSame(1400500, $pedido->total);
    }

    #[Test]
    public function sin_cliente_ni_lineas_no_se_guarda(): void
    {
        Livewire::test(Formulario::class)
            ->call('guardar')
            ->assertHasErrors(['cliente', 'lineas']);

        $this->assertSame(0, Pedido::count());
    }

    #[Test]
    public function un_descuento_mayor_al_autorizado_marca_el_campo(): void
    {
        Livewire::test(Formulario::class)
            ->call('abrirLinea')
            ->call('elegirProducto', $this->p1->id_producto)
            ->set('forma', 'METRO')
            ->set('cantidad', '7')
            ->set('descuento', '6')
            ->call('guardarLinea')
            ->assertHasErrors('descuento')
            ->assertSet('lineas', []);
    }

    #[Test]
    public function un_corte_de_mas_metros_que_el_rollo_se_rechaza(): void
    {
        Livewire::test(Formulario::class)
            ->call('abrirLinea')
            ->call('elegirProducto', $this->p1->id_producto)
            ->set('forma', 'METRO')
            ->set('cantidad', '60')
            ->call('guardarLinea')
            ->assertHasErrors('cantidad');
    }

    #[Test]
    public function si_falta_stock_el_borrador_queda_guardado_con_el_aviso(): void
    {
        Livewire::test(Formulario::class)
            ->call('elegirCliente', $this->cliente->id_cliente)
            ->call('abrirLinea')
            ->call('elegirProducto', $this->p1->id_producto)
            ->set('cantidad', '9')
            ->call('guardarLinea')
            ->call('confirmar')
            ->assertHasErrors('confirmar')
            ->assertNoRedirect();

        $this->assertSame('BORRADOR', Pedido::firstOrFail()->estado);
    }

    #[Test]
    public function un_pedido_confirmado_no_abre_el_formulario(): void
    {
        $pedido = $this->pedidoConfirmado();

        Livewire::test(Formulario::class, ['pedido' => $pedido])->assertRedirect(route('pedidos.detalle', $pedido));
    }

    #[Test]
    public function el_detalle_avanza_hasta_el_cobro(): void
    {
        $pedido = $this->pedidoConfirmado();

        Livewire::test(Detalle::class, ['pedido' => $pedido])
            ->assertSee('Marcar entregado')
            ->assertSee('Enviar comprobante')
            ->assertSeeHtml('href="https://wa.me/595971578168?text=')
            ->call('marcarEntregado')
            ->assertSee('Registrar cobro')
            ->call('registrarCobro')
            ->assertSee('Cobro registrado')
            ->assertDontSee('Anular pedido');

        $this->assertSame('COBRADO', $pedido->refresh()->estado);
    }

    #[Test]
    public function el_detalle_anula_y_repone_el_stock(): void
    {
        $pedido = $this->pedidoConfirmado();

        Livewire::test(Detalle::class, ['pedido' => $pedido])
            ->call('anular')
            ->assertSee('volvió al stock');

        $this->assertSame('ANULADO', $pedido->refresh()->estado);
        $this->assertSame(3, $this->p1->stock()->value('cantidad_cerrada'));
    }

    #[Test]
    public function el_listado_arranca_en_los_pendientes(): void
    {
        $confirmado = $this->pedidoConfirmado();
        $cobrado = $this->app->make(PedidoService::class)->registrarCobro(
            $this->app->make(PedidoService::class)->marcarEntregado($this->pedidoConfirmado())
        );

        Livewire::test(Listado::class)
            ->assertSee('N° '.$confirmado->numero())
            ->assertDontSee('N° '.$cobrado->numero())
            ->call('filtrar', 'cobrados')
            ->assertSee('N° '.$cobrado->numero())
            ->assertDontSee('N° '.$confirmado->numero());
    }

    #[Test]
    public function las_pantallas_se_abren_sin_datos(): void
    {
        $this->get(route('inicio'))->assertOk()->assertSee('Pedidos por confirmar y entregar')->assertSee('Todavía no hay pedidos cobrados');
        $this->get(route('pedidos.listado'))->assertOk()->assertSee('No hay pedidos pendientes');
        $this->get(route('pedidos.nuevo'))->assertOk()->assertSee('Agregar producto');
        $this->get(route('reportes.resultado'))->assertOk()->assertSee('Todavía no hay pedidos cobrados este mes');
    }

    #[Test]
    public function las_pantallas_se_abren_con_pedidos_en_todos_los_estados(): void
    {
        $pedidos = $this->app->make(PedidoService::class);
        $this->app->make(StockService::class)->ingresar($this->p1, 5, 'REPOSICION');
        $cobrado = $pedidos->registrarCobro($pedidos->marcarEntregado($this->pedidoConfirmado()));
        $pedidos->marcarEntregado($this->pedidoConfirmado());
        $pedidos->anular($this->pedidoConfirmado());
        $borrador = $pedidos->guardar(null, $this->cliente->id_cliente, [
            ['id_producto' => $this->p1->id_producto, 'tipo_venta' => 'METRO', 'cantidad' => 2, 'precio_unitario' => 25000, 'descuento_pct' => 20],
        ], 0);

        $this->get(route('inicio'))->assertOk()
            ->assertSee('Nicolasa Benítez')
            ->assertSee('1.134.000')
            ->assertSee('Ver los números');
        $this->get(route('pedidos.listado', ['filtro' => 'todos']))->assertOk()->assertSee('Anulado');
        $this->get(route('pedidos.detalle', $cobrado))->assertOk()->assertSee('Cobrado');
        $this->get(route('pedidos.editar', $borrador))->assertOk()->assertSee('retazo 20%');
        $this->get(route('reportes.resultado'))->assertOk()->assertSee('Pedidos cobrados')->assertSee('Hexagonal');
    }

    #[Test]
    public function el_resultado_no_pasa_del_mes_actual(): void
    {
        Livewire::withQueryParams(['mes' => '2099-01'])->test(Resultado::class)->assertSet('mes', '');

        Livewire::test(Resultado::class)
            ->call('anterior')
            ->assertSet('mes', now()->subMonthNoOverflow()->format('Y-m'))
            ->call('siguiente')
            ->assertSet('mes', '');
    }
}
