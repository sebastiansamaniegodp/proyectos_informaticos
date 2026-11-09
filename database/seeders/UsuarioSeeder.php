<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

class UsuarioSeeder extends Seeder
{
    /**
     * Usuarios de prueba para entrar al sistema en desarrollo, uno por rol.
     * Todos con la contraseña 123.
     */
    public function run(): void
    {
        $usuarios = [
            ['admin', 'Administrador', Rol::Admin],
            ['vendedor', 'Vendedor de Prueba', Rol::Vendedor],
            ['deposito', 'Depósito de Prueba', Rol::Deposito],
        ];

        foreach ($usuarios as [$username, $nombre, $rol]) {
            Usuario::updateOrCreate(
                ['username' => $username],
                [
                    'clave_hash' => '123',
                    'nombre' => $nombre,
                    'rol' => $rol,
                    'activo' => 'S',
                ],
            );
        }
    }
}
