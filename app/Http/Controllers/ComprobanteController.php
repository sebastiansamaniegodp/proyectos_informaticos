<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Services\ComprobanteService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Devuelve el comprobante del pedido en PDF. Por defecto se abre en el
 * navegador (el botón "Enviar comprobante" lo lee para compartirlo);
 * con ?descargar=1 se baja como archivo.
 */
class ComprobanteController extends Controller
{
    public function __invoke(Request $request, Pedido $pedido, ComprobanteService $comprobantes): Response
    {
        abort_unless($pedido->tieneComprobante(), 404);

        $pdf = $comprobantes->generar($pedido);
        $nombre = $comprobantes->nombreArchivo($pedido);

        $respuesta = $request->boolean('descargar') ? $pdf->download($nombre) : $pdf->stream($nombre);
        $respuesta->headers->set('Cache-Control', 'private, no-store');

        return $respuesta;
    }
}
