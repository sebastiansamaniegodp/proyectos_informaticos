<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Cliente (tabla CLIENTE del DER). Solo el nombre es obligatorio; el
 * documento, el teléfono y la dirección se pueden cargar después (DD-10).
 */
class Cliente extends Model
{
    protected $table = 'cliente';

    protected $primaryKey = 'id_cliente';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'documento',
        'telefono',
        'direccion_entrega',
        'activo',
    ];

    public function scopeActivos(Builder $query): void
    {
        $query->where('activo', 'S');
    }

    /**
     * Clientes cuyo nombre, teléfono o documento contienen lo buscado. El
     * nombre se compara sin tildes, así "nunez" encuentra "Núñez". En el
     * teléfono se comparan solo los dígitos, así "0981 234" encuentra
     * "0981-234-567".
     */
    public function scopeBuscar(Builder $query, string $busqueda): void
    {
        $busqueda = trim($busqueda);

        if ($busqueda === '') {
            return;
        }

        $patron = '%'.addcslashes(Str::lower(Str::ascii($busqueda)), '%_\\').'%';
        $digitos = preg_replace('/\D/', '', $busqueda);

        $query->where(function (Builder $query) use ($patron, $digitos) {
            $query->whereRaw("translate(lower(nombre), 'áéíóúüñ', 'aeiouun') like ?", [$patron])
                ->orWhere('documento', 'ilike', $patron);

            if ($digitos !== '') {
                $query->orWhereRaw("regexp_replace(telefono, '\\D', '', 'g') like ?", ['%'.$digitos.'%']);
            }
        });
    }

    /**
     * Letra con la que se agrupa el listado: la primera del nombre, sin tilde.
     * Lo que no empieza con letra va con "#".
     */
    public function inicial(): string
    {
        $letra = Str::upper(Str::substr(Str::ascii(trim($this->nombre)), 0, 1));

        return preg_match('/[A-Z]/', $letra) ? $letra : '#';
    }

    /**
     * Línea de abajo del nombre en el listado: 0981 234 567 · Salto del Guairá.
     */
    public function contacto(): string
    {
        return implode(' · ', array_filter([
            $this->telefono ?: 'Sin teléfono cargado',
            $this->direccion_entrega,
        ]));
    }
}
