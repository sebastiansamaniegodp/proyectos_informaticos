<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrada o salida de mercadería (tabla MOVIMIENTO_STOCK del DER). La
 * cantidad lleva signo: positiva entra, negativa sale (DD-07).
 *
 * Solo App\Services\StockService escribe en esta tabla.
 */
class MovimientoStock extends Model
{
    protected $table = 'movimiento_stock';

    protected $primaryKey = 'id_movimiento';

    public $timestamps = false;

    public const MOTIVOS_INGRESO = [
        'CARGA_INICIAL' => 'Carga inicial',
        'REPOSICION' => 'Reposición',
    ];

    public const MOTIVOS_AJUSTE = [
        'ROTURA' => 'Rotura',
        'MUESTRA' => 'Muestra',
        'OBSEQUIO' => 'Obsequio',
        'INVENTARIO' => 'Diferencia de inventario',
    ];

    protected $fillable = [
        'id_producto',
        'id_rollo',
        'id_pedido',
        'tipo',
        'motivo',
        'cantidad',
        'unidad',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
            'fecha' => 'datetime',
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

    /**
     * Lo que se muestra en el historial: "Venta", "Ajuste · Rotura",
     * "Ingreso · Reposición".
     */
    public function etiqueta(): string
    {
        $tipo = [
            'INGRESO' => 'Ingreso',
            'VENTA' => 'Venta',
            'AJUSTE' => 'Ajuste',
            'ANULACION' => 'Anulación',
        ][$this->tipo];

        $motivo = (self::MOTIVOS_INGRESO + self::MOTIVOS_AJUSTE)[$this->motivo] ?? null;

        return $motivo ? "{$tipo} · {$motivo}" : $tipo;
    }

    /**
     * Cantidad con signo y unidad: "+2 rollos", "−7 m".
     */
    public function cantidadConSigno(): string
    {
        $signo = $this->cantidad > 0 ? '+' : '−';
        $cantidad = abs($this->cantidad);

        if ($this->unidad === 'METRO') {
            return $signo.Stock::formatearCantidad($cantidad).' m';
        }

        return $signo.Producto::cantidadConUnidad($cantidad, $this->unidad);
    }
}
