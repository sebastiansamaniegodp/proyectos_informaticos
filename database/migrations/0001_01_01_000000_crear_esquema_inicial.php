<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea el esquema completo ejecutando el script SQL v1.1 de la tarea 2.1 (DT-07).
 *
 * El archivo database/sql/modelo_datos_postgresql.sql no se modifica. Todo cambio
 * posterior al esquema va en una migracion nueva, nunca editando esta.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sql = file_get_contents(database_path('sql/modelo_datos_postgresql.sql'));

        // En PostgreSQL, Laravel ya corre cada migracion dentro de una transaccion.
        // Un COMMIT dentro del script la cerraria a mitad de camino, por eso se quitan
        // BEGIN y COMMIT del texto en memoria (no del archivo). Asi el script sigue
        // corriendo completo o no corriendo.
        $sql = preg_replace('/^\s*(BEGIN|COMMIT)\s*;\s*$/mi', '', $sql);

        DB::unprepared($sql);
    }

    public function down(): void
    {
        // Mismo bloque de limpieza que el script.
        DB::unprepared(<<<'SQL'
            DROP VIEW  IF EXISTS vw_stock_disponible CASCADE;
            DROP TABLE IF EXISTS movimiento_stock CASCADE;
            DROP TABLE IF EXISTS pedido_detalle   CASCADE;
            DROP TABLE IF EXISTS pedido           CASCADE;
            DROP TABLE IF EXISTS rollo_abierto    CASCADE;
            DROP TABLE IF EXISTS stock            CASCADE;
            DROP TABLE IF EXISTS producto         CASCADE;
            DROP TABLE IF EXISTS familia          CASCADE;
            DROP TABLE IF EXISTS cliente          CASCADE;
            DROP TABLE IF EXISTS usuario          CASCADE;
            SQL);
    }
};
