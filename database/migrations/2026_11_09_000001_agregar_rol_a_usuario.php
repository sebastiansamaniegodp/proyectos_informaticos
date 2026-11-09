<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega el rol del usuario. Hay tres: ADMIN, VENDEDOR y DEPOSITO (ver
 * App\Enums\Rol para lo que puede hacer cada uno).
 *
 * Los usuarios que ya existían entraban a todo, así que quedan como ADMIN.
 * Un usuario nuevo sin rol indicado nace como VENDEDOR.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE usuario ADD COLUMN rol VARCHAR(10) DEFAULT 'ADMIN' NOT NULL;
            ALTER TABLE usuario ALTER COLUMN rol SET DEFAULT 'VENDEDOR';
            ALTER TABLE usuario ADD CONSTRAINT ck_usuario_rol CHECK (rol IN ('ADMIN','VENDEDOR','DEPOSITO'));
            COMMENT ON COLUMN usuario.rol IS 'ADMIN = todo, VENDEDOR = pedidos y clientes, DEPOSITO = movimientos de stock';
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE usuario DROP CONSTRAINT IF EXISTS ck_usuario_rol;
            ALTER TABLE usuario DROP COLUMN IF EXISTS rol;
            SQL);
    }
};
