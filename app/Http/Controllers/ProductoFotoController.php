<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve la foto del producto desde el disco local. Las fotos no están en
 * public/, así solo las ve quien inició sesión y no hace falta storage:link.
 */
class ProductoFotoController extends Controller
{
    public function __invoke(Producto $producto): StreamedResponse
    {
        abort_unless($producto->foto && Storage::disk('local')->exists($producto->foto), 404);

        // La URL lleva el nombre del archivo, que cambia con cada foto nueva:
        // se puede guardar en caché sin miedo a mostrar una vieja.
        return Storage::disk('local')->response($producto->foto, null, [
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
