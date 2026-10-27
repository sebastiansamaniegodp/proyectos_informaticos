<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rollo ya empezado (tabla ROLLO_ABIERTO del DER), con sus metros restantes.
 * Hay una fila solo por rollo cortado; los enteros son el contador de STOCK
 * (DD-02).
 *
 * Solo App\Services\StockService escribe en esta tabla.
 */
class RolloAbierto extends Model
{
    protected $table = 'rollo_abierto';

    protected $primaryKey = 'id_rollo';

    public $timestamps = false;

    /** Por debajo de estos metros lo que queda del rollo es un retazo (SUP-05, RN-12). */
    public const METROS_RETAZO = 3;

    /** Descuento que se ofrece por un retazo (SUP-05, RN-12). Lo aplica el pedido. */
    public const DESCUENTO_RETAZO = 20;

    protected function casts(): array
    {
        return [
            'metros_iniciales' => 'float',
            'metros_restantes' => 'float',
            'fecha_apertura' => 'datetime',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function scopeAbiertos(Builder $query): void
    {
        $query->where('estado', 'ABIERTO');
    }

    public function esRetazo(): bool
    {
        return $this->estado === 'ABIERTO'
            && $this->metros_restantes > 0
            && $this->metros_restantes < self::METROS_RETAZO;
    }
}
