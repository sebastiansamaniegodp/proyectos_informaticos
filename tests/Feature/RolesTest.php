<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\Producto;
use App\Models\Usuario;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Qué pantallas abre cada rol y cuáles le devuelven 403.
 */
class RolesTest extends TestCase
{
    use RefreshDatabase;

    private function entrarComo(Rol $rol): Usuario
    {
        $usuario = Usuario::create(['username' => 'prueba', 'clave_hash' => 'secreta', 'nombre' => 'Prueba', 'rol' => $rol, 'activo' => 'S']);
        $this->actingAs($usuario);

        return $usuario;
    }

    /** Rutas con parámetro de producto, armadas con el primero del catálogo. */
    private function url(string $ruta): string
    {
        $conProducto = ['productos.editar', 'stock.detalle', 'stock.ingreso', 'stock.ajuste'];

        return in_array($ruta, $conProducto, true)
            ? route($ruta, Producto::query()->firstOrFail())
            : route($ruta);
    }

    public static function accesos(): array
    {
        $todas = [
            'productos.catalogo', 'stock.listado', 'stock.detalle',
            'pedidos.listado', 'pedidos.nuevo', 'clientes.listado', 'clientes.nuevo',
            'stock.ingreso', 'stock.ajuste', 'productos.nuevo', 'productos.editar',
            'reportes.resultado',
        ];
        $comunes = ['productos.catalogo', 'stock.listado', 'stock.detalle'];

        return [
            'admin' => [Rol::Admin, $todas, []],
            'vendedor' => [
                Rol::Vendedor,
                [...$comunes, 'pedidos.listado', 'pedidos.nuevo', 'clientes.listado', 'clientes.nuevo'],
                ['stock.ingreso', 'stock.ajuste', 'productos.nuevo', 'productos.editar', 'reportes.resultado'],
            ],
            'deposito' => [
                Rol::Deposito,
                [...$comunes, 'stock.ingreso', 'stock.ajuste'],
                ['pedidos.listado', 'pedidos.nuevo', 'clientes.listado', 'clientes.nuevo', 'productos.nuevo', 'productos.editar', 'reportes.resultado'],
            ],
        ];
    }

    #[Test]
    #[DataProvider('accesos')]
    public function cada_rol_entra_solo_a_lo_suyo(Rol $rol, array $permitidas, array $prohibidas): void
    {
        $this->entrarComo($rol);

        foreach ($permitidas as $ruta) {
            $this->get($this->url($ruta))->assertOk();
        }

        foreach ($prohibidas as $ruta) {
            $this->get($this->url($ruta))->assertForbidden();
        }
    }

    #[Test]
    public function inicio_es_del_admin_y_a_los_demas_los_manda_a_su_pantalla(): void
    {
        $this->entrarComo(Rol::Admin);
        $this->get(route('inicio'))->assertOk();

        $this->actingAs(Usuario::create(['username' => 'v', 'clave_hash' => 'x', 'nombre' => 'V', 'rol' => Rol::Vendedor, 'activo' => 'S']));
        $this->get(route('inicio'))->assertRedirect(route('pedidos.listado'));

        $this->actingAs(Usuario::create(['username' => 'd', 'clave_hash' => 'x', 'nombre' => 'D', 'rol' => Rol::Deposito, 'activo' => 'S']));
        $this->get(route('inicio'))->assertRedirect(route('stock.listado'));
    }

    #[Test]
    public function el_menu_y_los_botones_muestran_solo_lo_permitido(): void
    {
        $this->entrarComo(Rol::Deposito);

        $this->get(route('stock.detalle', Producto::query()->firstOrFail()))
            ->assertOk()
            ->assertSee('Depósito')
            ->assertSee('Ingreso')
            ->assertDontSee('Pedidos')
            ->assertDontSee('Resultado del mes');

        $this->get(route('productos.catalogo'))->assertOk()->assertDontSee('Nuevo producto');
    }

    #[Test]
    public function un_usuario_sin_rol_indicado_nace_como_vendedor(): void
    {
        $usuario = Usuario::create(['username' => 'nuevo', 'clave_hash' => 'x', 'nombre' => 'Nuevo', 'activo' => 'S']);

        $this->assertSame(Rol::Vendedor, $usuario->fresh()->rol);
    }

    #[Test]
    public function el_seeder_deja_un_usuario_por_rol_y_cada_uno_entra_a_su_pantalla(): void
    {
        $this->seed(UsuarioSeeder::class);

        $esperado = ['admin' => 'inicio', 'vendedor' => 'pedidos.listado', 'deposito' => 'stock.listado'];

        foreach ($esperado as $username => $ruta) {
            $this->post('/login', ['username' => $username, 'password' => '123'])->assertRedirect(route($ruta));
            $this->post(route('logout'));
        }
    }
}
