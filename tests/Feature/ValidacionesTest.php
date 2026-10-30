<?php

namespace Tests\Feature;

use App\Livewire\Clientes\Formulario as ClienteFormulario;
use App\Livewire\Productos\Formulario as ProductoFormulario;
use App\Livewire\Stock\Ajuste;
use App\Livewire\Stock\Ingreso;
use App\Models\Cliente;
use App\Models\Familia;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Validaciones de los formularios: campos obligatorios, tipo de dato y
 * formato. Usa los productos de ejemplo del script v1.1.
 */
class ValidacionesTest extends TestCase
{
    use RefreshDatabase;

    private function hexagonal(): Producto
    {
        return Producto::where('codigo', 'HEX-12-150-50')->firstOrFail();
    }

    /**
     * Un producto nuevo del hexagonal, completo y válido.
     */
    private function productoValido(): Testable
    {
        return Livewire::test(ProductoFormulario::class)
            ->set('id_familia', Familia::where('nombre', 'Tejido hexagonal')->value('id_familia'))
            ->set('altura', '100')
            ->set('abertura', '1"')
            ->set('calibre', 'n° 18')
            ->set('unidad_venta', 'ROLLO')
            ->set('fraccionable', true)
            ->set('metraje', '50')
            ->set('precio_venta', '900.000')
            ->set('precio_metro', '20.000')
            ->set('precio_mayorista', '850.000')
            ->set('costo', '675.000');
    }

    #[Test]
    public function un_producto_completo_se_guarda(): void
    {
        $this->productoValido()->call('guardar')->assertHasNoErrors();

        $this->assertDatabaseHas('producto', ['codigo' => 'HEX-1-100-50', 'precio_venta' => 900000, 'costo' => 675000]);
    }

    #[Test]
    public function un_precio_con_letras_se_rechaza(): void
    {
        $this->productoValido()->set('precio_venta', '90O.000')->call('guardar')->assertHasErrors('precio_venta');
    }

    #[Test]
    public function la_altura_con_letras_se_rechaza(): void
    {
        $this->productoValido()->set('altura', '1a0')->call('guardar')->assertHasErrors('altura');
    }

    #[Test]
    public function el_costo_es_obligatorio(): void
    {
        $this->productoValido()->set('costo', '')->call('guardar')->assertHasErrors(['costo' => 'required']);
    }

    #[Test]
    public function el_metro_no_puede_costar_mas_que_el_rollo(): void
    {
        $this->productoValido()->set('precio_metro', '950.000')->call('guardar')->assertHasErrors('precio_metro');
    }

    #[Test]
    public function el_mayorista_no_puede_superar_el_precio_de_lista(): void
    {
        $this->productoValido()->set('precio_mayorista', '950.000')->call('guardar')->assertHasErrors('precio_mayorista');
    }

    #[Test]
    public function la_abertura_del_hexagonal_va_en_pulgadas(): void
    {
        $this->productoValido()->set('abertura', '2 cm')->call('guardar')->assertHasErrors('abertura');
    }

    #[Test]
    public function la_abertura_del_romboidal_va_en_centimetros(): void
    {
        $this->productoValido()
            ->set('id_familia', Familia::where('nombre', 'Tejido romboidal')->value('id_familia'))
            ->set('fraccionable', false)
            ->set('abertura', '2"')
            ->call('guardar')
            ->assertHasErrors('abertura');
    }

    #[Test]
    public function un_producto_dado_de_baja_sale_del_catalogo_y_vuelve(): void
    {
        $producto = $this->hexagonal();

        Livewire::test(ProductoFormulario::class, ['producto' => $producto])->call('cambiarEstado');
        $this->assertSame('N', $producto->refresh()->activo);

        Livewire::test(ProductoFormulario::class, ['producto' => $producto])->call('cambiarEstado');
        $this->assertSame('S', $producto->refresh()->activo);
    }

    #[Test]
    public function un_cliente_completo_se_guarda_con_la_cedula_sin_puntos(): void
    {
        Livewire::test(ClienteFormulario::class)
            ->set('nombre', 'María Núñez')
            ->set('documento', '4.567.890')
            ->set('telefono', '0981 234 567')
            ->set('direccion_entrega', 'Salto del Guairá')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cliente', ['nombre' => 'María Núñez', 'documento' => '4567890']);
    }

    #[Test]
    public function los_datos_del_cliente_con_formato_invalido_se_rechazan(): void
    {
        Livewire::test(ClienteFormulario::class)
            ->set('nombre', '12345')
            ->set('documento', 'ABC123')
            ->set('telefono', '09x1')
            ->set('direccion_entrega', 'a')
            ->call('guardar')
            ->assertHasErrors(['nombre', 'documento', 'telefono', 'direccion_entrega']);
    }

    #[Test]
    public function no_se_repite_el_documento_de_otro_cliente(): void
    {
        Cliente::create(['nombre' => 'Juan Pérez', 'documento' => '80012345-6', 'telefono' => '0981111222', 'direccion_entrega' => 'Asunción', 'activo' => 'S']);

        Livewire::test(ClienteFormulario::class)
            ->set('nombre', 'Otro')
            ->set('documento', '80012345-6')
            ->set('telefono', '0982 333 444')
            ->set('direccion_entrega', 'Encarnación')
            ->call('guardar')
            ->assertHasErrors(['documento' => 'unique']);
    }

    #[Test]
    public function el_ingreso_de_stock_exige_un_entero_positivo(): void
    {
        foreach (['', '0', '2,5', 'dos'] as $cantidad) {
            Livewire::test(Ingreso::class, ['producto' => $this->hexagonal()])
                ->set('cantidad', $cantidad)
                ->call('guardar')
                ->assertHasErrors('cantidad');
        }
    }

    #[Test]
    public function el_ajuste_exige_motivo_y_metros_validos(): void
    {
        Livewire::test(Ajuste::class, ['producto' => $this->hexagonal()])
            ->set('cantidad', '1')
            ->call('guardar')
            ->assertHasErrors('motivo');

        Livewire::test(Ajuste::class, ['producto' => $this->hexagonal()])
            ->set('motivo', 'MUESTRA')
            ->set('que', 'METROS')
            ->set('cantidad', '1,555')
            ->call('guardar')
            ->assertHasErrors('cantidad');
    }
}
