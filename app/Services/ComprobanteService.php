<?php

namespace App\Services;

use App\Exceptions\ErrorDePedido;
use App\Models\Pedido;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentoPdf;
use Illuminate\Support\Str;

/**
 * Comprobante del pedido en PDF, para mandar por WhatsApp (DD-08). Es un
 * comprobante interno, sin validez fiscal.
 *
 * Se genera desde CONFIRMADO en adelante (SP-09): un borrador todavía puede
 * cambiar y un pedido anulado no se cobra.
 */
class ComprobanteService
{
    public function generar(Pedido $pedido): DocumentoPdf
    {
        if (! $pedido->tieneComprobante()) {
            throw new ErrorDePedido('El comprobante se genera cuando el pedido está confirmado.');
        }

        $pedido->loadMissing(['cliente', 'lineas.producto.familia']);

        // Con el recorte de fuentes el PDF lleva solo las letras que usa:
        // pesa decenas de KB en vez de casi un MB, y llega rápido por WhatsApp.
        return Pdf::loadView('pdf.comprobante', [
            'pedido' => $pedido,
            'logo' => $this->logo(),
            'negocio' => config('negocio'),
        ])->setPaper('a5')->setOption('isFontSubsettingEnabled', true);
    }

    /**
     * Nombre del archivo: Pedido-00124-Nicolasa-Benitez.pdf. Sin tildes ni
     * espacios, para que llegue igual por WhatsApp.
     */
    public function nombreArchivo(Pedido $pedido): string
    {
        $cliente = Str::title(Str::limit(Str::slug(Str::ascii($pedido->cliente->nombre)), 40, ''));

        return 'Pedido-'.$pedido->numero().($cliente !== '' ? '-'.$cliente : '').'.pdf';
    }

    /**
     * El logo va embebido en el PDF: dompdf no lee archivos de afuera del
     * proyecto ni de internet.
     */
    private function logo(): ?string
    {
        $archivo = public_path('img/logo.png');

        return is_file($archivo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($archivo)) : null;
    }
}
