<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega la foto del producto, que pide la pantalla "Alta de producto" del
 * prototipo y no estaba en el script v1.1.
 *
 * Se guarda solo la ruta del archivo dentro del disco local (storage/app/private);
 * la imagen se sirve por una ruta con login, no desde public/.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE producto ADD COLUMN foto VARCHAR(255) NULL;
            COMMENT ON COLUMN producto.foto IS 'Ruta de la foto en el disco local, NULL si no tiene';
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('ALTER TABLE producto DROP COLUMN IF EXISTS foto;');
    }
};
