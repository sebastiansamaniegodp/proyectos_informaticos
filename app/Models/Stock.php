<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Piezas cerradas de un producto (tabla STOCK del DER): rollos sin abrir,
 * kilos o unidades. Son intercambiables, por eso van como contador (DD-02).
 *
 * Solo App\Services\StockService escribe en esta tabla.
 */
class Stock extends Model
{
    protected $table = 'stock';

    protected $primaryKey = 'id_producto';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cantidad_cerrada' => 'integer',
            'fecha_actualizacion' => 'datetime',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    /**
     * Metros o piezas con coma decimal y sin ceros de más: 43 da "43", 7.5
     * da "7,5" y 1250 da "1.250".
     */
    public static function formatearCantidad(float|string|null $metros): string
    {
        $texto = number_format((float) $metros, 2, ',', '.');

        return rtrim(rtrim($texto, '0'), ',');
    }
}
