<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * El seeder del listado de precios es de la tarea 3.3.
     */
    public function run(): void
    {
        $this->call(UsuarioSeeder::class);
    }
}
