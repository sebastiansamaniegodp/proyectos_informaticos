<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Usuarios\Formulario;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La pantalla de usuarios: alta, cambio de rol y de contraseña, y
 * desactivar. Solo entra el administrador.
 */
class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->crear('jefa', Rol::Admin);
        $this->actingAs($this->admin);
    }

    private function crear(string $username, Rol $rol): Usuario
    {
        return Usuario::create(['username' => $username, 'clave_hash' => 'secreta', 'nombre' => ucfirst($username), 'rol' => $rol, 'activo' => 'S']);
    }

    #[Test]
    public function solo_el_admin_entra_a_usuarios(): void
    {
        $otro = $this->crear('otro', Rol::Vendedor);

        $this->get(route('usuarios.listado'))->assertOk()->assertSee('Jefa')->assertSee('(vos)')->assertSee('Vendedor');
        $this->get(route('usuarios.nuevo'))->assertOk();
        $this->get(route('usuarios.editar', $otro))->assertOk();

        foreach ([$otro, $this->crear('depo', Rol::Deposito)] as $usuario) {
            $this->actingAs($usuario);
            $this->get(route('usuarios.listado'))->assertForbidden();
            $this->get(route('usuarios.nuevo'))->assertForbidden();
            $this->get(route('usuarios.editar', $this->admin))->assertForbidden();
            $this->get(route('stock.listado'))->assertOk()->assertDontSee('Usuarios');
        }
    }

    #[Test]
    public function se_crea_un_usuario_con_rol_y_puede_entrar(): void
    {
        Livewire::test(Formulario::class)
            ->set('nombre', '  Ana   Gómez ')
            ->set('username', 'Ana.Gomez')
            ->set('rol', 'DEPOSITO')
            ->set('clave', 'tejidos1')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('usuarios.listado'));

        $ana = Usuario::where('username', 'ana.gomez')->firstOrFail();
        $this->assertSame('Ana Gómez', $ana->nombre);
        $this->assertSame(Rol::Deposito, $ana->rol);
        $this->assertSame('S', $ana->activo);
        $this->assertTrue(Hash::check('tejidos1', $ana->clave_hash));

        $this->post(route('logout'));
        $this->post('/login', ['username' => 'ana.gomez', 'password' => 'tejidos1'])->assertRedirect(route('stock.listado'));
    }

    #[Test]
    public function el_alta_valida_cada_campo(): void
    {
        $this->crear('repetido', Rol::Vendedor);

        Livewire::test(Formulario::class)
            ->call('guardar')
            ->assertHasErrors(['nombre', 'username', 'clave'])
            ->set('nombre', 'Ana')
            ->set('username', 'con espacio')
            ->set('clave', '12')
            ->set('rol', 'JEFE')
            ->call('guardar')
            ->assertHasErrors(['username', 'clave', 'rol'])
            ->assertSee('al menos 3 caracteres')
            ->set('clave', '123')
            ->call('guardar')
            ->assertHasNoErrors(['clave'])
            ->set('username', 'REPETIDO')
            ->call('guardar')
            ->assertHasErrors(['username'])
            ->assertSee('Ya hay alguien con ese usuario.');

        $this->assertSame(2, Usuario::count());
    }

    #[Test]
    public function al_editar_se_cambia_el_rol_y_la_clave_vacia_no_se_toca(): void
    {
        $otro = $this->crear('otro', Rol::Vendedor);
        $hash = $otro->clave_hash;

        Livewire::test(Formulario::class, ['usuario' => $otro])
            ->assertSet('username', 'otro')
            ->assertSet('rol', 'VENDEDOR')
            ->set('rol', 'ADMIN')
            ->call('guardar')
            ->assertHasNoErrors();

        $otro->refresh();
        $this->assertSame(Rol::Admin, $otro->rol);
        $this->assertSame($hash, $otro->clave_hash);

        Livewire::test(Formulario::class, ['usuario' => $otro])
            ->set('clave', 'nuevaclave')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('nuevaclave', $otro->fresh()->clave_hash));
    }

    #[Test]
    public function el_admin_no_puede_cambiarse_el_rol_ni_desactivarse(): void
    {
        Livewire::test(Formulario::class, ['usuario' => $this->admin])
            ->set('rol', 'VENDEDOR')
            ->call('guardar')
            ->assertHasErrors(['rol'])
            ->call('cambiarEstado')
            ->assertHasErrors(['estado']);

        $this->admin->refresh();
        $this->assertSame(Rol::Admin, $this->admin->rol);
        $this->assertSame('S', $this->admin->activo);

        // Sí puede cambiarse el nombre y la contraseña.
        Livewire::test(Formulario::class, ['usuario' => $this->admin])
            ->set('nombre', 'La Jefa')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('La Jefa', $this->admin->fresh()->nombre);
    }

    #[Test]
    public function un_usuario_desactivado_no_entra_y_pierde_la_sesion_abierta(): void
    {
        $otro = $this->crear('otro', Rol::Vendedor);

        Livewire::test(Formulario::class, ['usuario' => $otro])->call('cambiarEstado')->assertRedirect(route('usuarios.listado'));
        $this->assertSame('N', $otro->fresh()->activo);

        // Con la sesión todavía abierta, el próximo pedido lo saca al login.
        $this->actingAs($otro->fresh());
        $this->get(route('pedidos.listado'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post('/login', ['username' => 'otro', 'password' => 'secreta'])->assertSessionHasErrors('username');

        // Reactivado, vuelve a entrar.
        $this->actingAs($this->admin);
        Livewire::test(Formulario::class, ['usuario' => $otro->fresh()])->call('cambiarEstado');
        $this->post(route('logout'));
        $this->post('/login', ['username' => 'otro', 'password' => 'secreta'])->assertRedirect(route('pedidos.listado'));
    }
}
