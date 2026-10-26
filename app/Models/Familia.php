<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Familia de productos (tabla FAMILIA del DER). Las siete del listado del cliente.
 */
class Familia extends Model
{
    protected $table = 'familia';

    protected $primaryKey = 'id_familia';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'activo',
    ];

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'id_familia', 'id_familia');
    }

    public function scopeActivas(Builder $query): void
    {
        $query->where('activo', 'S')->orderBy('id_familia');
    }

    /**
     * Nombre corto para los filtros del catálogo: "Tejido hexagonal" pasa a
     * "Hexagonal" y "Alambres y cordones" a "Alambres".
     */
    public function nombreCorto(): string
    {
        $nombre = preg_replace('/^Tejido\s+/i', '', $this->nombre);

        return Str::ucfirst(Str::before(Str::before($nombre, ','), ' '));
    }

    /**
     * Las tres primeras letras del nombre corto, que encabezan el código del
     * producto: HEX, TEL, FAP, SEG, ROM, ALA, GRA.
     */
    public function prefijoCodigo(): string
    {
        return Str::upper(Str::substr(Str::ascii($this->nombreCorto()), 0, 3));
    }
}
