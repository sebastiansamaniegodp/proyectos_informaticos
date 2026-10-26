<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;

class UsuarioSeeder extends Seeder
{
    /**
     * Usuario de prueba para entrar al sistema en desarrollo (admin / 123).
     */
    public function run(): void
    {
        Usuario::updateOrCreate(
            ['username' => 'admin'],
            [
                'clave_hash' => '123',
                'nombre' => 'Administrador',
                'activo' => 'S',
            ],
        );
    }
}
