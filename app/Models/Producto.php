<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Producto (tabla PRODUCTO del DER). Una fila por variante comercial: cada
 * altura y cada presentación es un producto distinto, con precio y stock
 * propios (DD-01).
 */
class Producto extends Model
{
    protected $table = 'producto';

    protected $primaryKey = 'id_producto';

    public $timestamps = false;

    public const UNIDADES = [
        'ROLLO' => 'Rollo',
        'METRO' => 'Metro',
        'KILO' => 'Kilo',
        'UNIDAD' => 'Unidad',
    ];

    protected $fillable = [
        'codigo',
        'descripcion',
        'id_familia',
        'unidad_venta',
        'fraccionable',
        'metraje_rollo',
        'altura_cm',
        'abertura',
        'calibre',
        'precio_venta',
        'precio_metro',
        'precio_mayorista',
        'costo',
        'activo',
        'foto',
    ];

    protected function casts(): array
    {
        return [
            'metraje_rollo' => 'integer',
            'altura_cm' => 'integer',
            'precio_venta' => 'integer',
            'precio_metro' => 'integer',
            'precio_mayorista' => 'integer',
            'costo' => 'integer',
        ];
    }

    public function familia(): BelongsTo
    {
        return $this->belongsTo(Familia::class, 'id_familia', 'id_familia');
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', 'S');
    }

    public function seCorta(): bool
    {
        return $this->fraccionable === 'S';
    }

    public function medidas(): string
    {
        return self::armarMedidas($this->abertura, $this->calibre, $this->altura_cm, $this->metraje_rollo);
    }

    public function fotoUrl(): ?string
    {
        if (! $this->foto) {
            return null;
        }

        // El nombre del archivo cambia con cada foto nueva, así el navegador
        // no muestra la anterior desde su caché.
        return route('productos.foto', ['producto' => $this, 'v' => basename($this->foto)]);
    }

    /**
     * Código del producto: prefijo de la familia, abertura (o el calibre si
     * no tiene abertura), altura y presentación (los metros del rollo, o KG
     * si se vende por kilo). Lo que falta se omite. Por ejemplo:
     * HEX-12-150-50 para el hexagonal de 1/2" de 1,50 m en rollo de 50 m, y
     * ALA-16-KG para el alambre n° 16 por kilo.
     */
    public static function armarCodigo(Familia $familia, ?string $abertura, ?string $calibre, ?int $alturaCm, string $unidadVenta, ?int $metrajeRollo): string
    {
        $presentacion = match ($unidadVenta) {
            'ROLLO' => $metrajeRollo,
            'KILO' => 'KG',
            default => null,
        };

        $partes = [
            $familia->prefijoCodigo(),
            self::primerNumero($abertura) ?? self::primerNumero($calibre),
            $alturaCm,
            $presentacion,
        ];

        return implode('-', array_filter($partes, fn ($parte) => $parte !== null && $parte !== ''));
    }

    /**
     * Línea de medidas del catálogo, por ejemplo: 1/2" n° 24 · 1,50 m × 50 m.
     */
    public static function armarMedidas(?string $abertura, ?string $calibre, ?int $alturaCm, ?int $metrajeRollo): string
    {
        $malla = trim(($abertura ?? '').' '.($calibre ?? ''));

        $tamano = array_filter([
            $alturaCm ? number_format($alturaCm / 100, 2, ',', '.').' m' : null,
            $metrajeRollo ? $metrajeRollo.' m' : null,
        ]);

        return implode(' · ', array_filter([$malla, implode(' × ', $tamano)]));
    }

    public static function formatearPrecio(?int $precio): string
    {
        return $precio === null ? '' : number_format($precio, 0, ',', '.');
    }

    /**
     * Solo los dígitos del primer número de la abertura o del calibre:
     * 1/2" da 12, "5 x 5 cm" da 5 y "n° 16" da 16.
     */
    private static function primerNumero(?string $texto): ?string
    {
        $texto = strtr($texto ?? '', ['½' => '1/2', '¼' => '1/4', '¾' => '3/4']);

        if (! preg_match('/\d+(?:[.,]\d+)?(?:\s*\/\s*\d+)?/', $texto, $numero)) {
            return null;
        }

        return preg_replace('/\D/', '', $numero[0]);
    }
}
