<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pedido (tabla PEDIDO del DER), con sus cinco estados (DD-06, RN-26).
 *
 * Solo App\Services\PedidoService escribe en esta tabla y en sus líneas.
 */
class Pedido extends Model
{
    protected $table = 'pedido';

    protected $primaryKey = 'id_pedido';

    public $timestamps = false;

    public const ESTADOS = [
        'BORRADOR' => 'Borrador',
        'CONFIRMADO' => 'Confirmado',
        'ENTREGADO' => 'Entregado',
        'COBRADO' => 'Cobrado',
        'ANULADO' => 'Anulado',
    ];

    /** Pedidos por confirmar o sin entregar: el reporte de pendientes (RN-26, REP-07). */
    public const PENDIENTES = ['BORRADOR', 'CONFIRMADO'];

    /** Desde CONFIRMADO en adelante se puede mandar el comprobante (SP-09). */
    public const CON_COMPROBANTE = ['CONFIRMADO', 'ENTREGADO', 'COBRADO'];

    protected $fillable = [
        'id_cliente',
        'estado',
        'monto_flete',
        'subtotal',
        'total',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'monto_flete' => 'integer',
            'subtotal' => 'integer',
            'total' => 'integer',
            'fecha_pedido' => 'datetime',
            'fecha_confirmacion' => 'datetime',
            'fecha_entrega' => 'datetime',
            'fecha_cobro' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(PedidoDetalle::class, 'id_pedido', 'id_pedido')->orderBy('nro_linea');
    }

    public function scopeEnEstado(Builder $query, string|array $estados): void
    {
        $query->whereIn('estado', (array) $estados);
    }

    public function esBorrador(): bool
    {
        return $this->estado === 'BORRADOR';
    }

    public function tieneComprobante(): bool
    {
        return in_array($this->estado, self::CON_COMPROBANTE, true);
    }

    public function nombreEstado(): string
    {
        return self::ESTADOS[$this->estado];
    }

    /**
     * Número que se muestra y va en el comprobante: 00124.
     */
    public function numero(): string
    {
        return self::formatearNumero($this->id_pedido);
    }

    /**
     * Una línea con lo que lleva el pedido, para los listados: "1 rollo + 7 m
     * Hexagonal 1,50". Las líneas del mismo producto van juntas; después del
     * segundo producto se resume en "y N más".
     */
    public function resumen(): string
    {
        $productos = $this->lineas->groupBy('id_producto')->map(function ($lineas) {
            $cantidades = $lineas->map(fn (PedidoDetalle $linea) => $linea->cantidadCorta())->implode(' + ');

            return $cantidades.' '.$lineas->first()->producto->nombreCorto();
        })->values();

        if ($productos->count() > 2) {
            return $productos->take(2)->implode(', ').' y '.($productos->count() - 2).' más';
        }

        return $productos->implode(', ');
    }

    public static function formatearNumero(int $idPedido): string
    {
        return str_pad((string) $idPedido, 5, '0', STR_PAD_LEFT);
    }
}
