<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea del pedido (tabla PEDIDO_DETALLE del DER). El precio y el costo se
 * copian del producto: un cambio posterior en el producto no altera las
 * líneas ya cargadas (RN-19, RN-34).
 *
 * La clave es compuesta (id_pedido, nro_linea) y Eloquent no las maneja:
 * las líneas se leen como relación del pedido y solo las escribe
 * App\Services\PedidoService, que mientras el pedido está en borrador las
 * reemplaza todas juntas.
 */
class PedidoDetalle extends Model
{
    protected $table = 'pedido_detalle';

    protected $primaryKey = 'nro_linea';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
            'precio_unitario' => 'integer',
            'descuento_pct' => 'float',
            'subtotal' => 'integer',
            'costo_unitario' => 'integer',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function rollo(): BelongsTo
    {
        return $this->belongsTo(RolloAbierto::class, 'id_rollo', 'id_rollo');
    }

    public function esCorte(): bool
    {
        return $this->tipo_venta === 'METRO';
    }

    /**
     * Cantidad con su unidad: "7 m", "1 rollo", "12 kilos".
     */
    public function cantidadCorta(): string
    {
        return self::escribirCantidad($this->cantidad, $this->tipo_venta);
    }

    /**
     * Costo de la línea: cantidad × costo unitario copiado al confirmar
     * (RN-33, RN-34), en guaraníes enteros (SP-02). Null si el producto no
     * tenía costo cargado.
     */
    public function costo(): ?int
    {
        return $this->costo_unitario === null ? null : (int) round($this->cantidad * $this->costo_unitario);
    }

    public static function escribirCantidad(float $cantidad, string $tipoVenta): string
    {
        return $tipoVenta === 'METRO'
            ? Stock::formatearCantidad($cantidad).' m'
            : Producto::cantidadConUnidad($cantidad, $tipoVenta);
    }
}
