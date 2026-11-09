@php
    use App\Models\Producto;
    use App\Models\RolloAbierto;

    $cliente = $pedido->cliente;
    $gs = fn (int $importe) => Producto::formatearPrecio($importe);

    // Solo los datos que el cliente tiene cargados: nada de "null" ni
    // renglones vacíos (DD-10, COMP-03).
    $datosCliente = array_filter([
        'Documento' => $cliente->documento,
        'Teléfono' => $cliente->telefono,
        'Entrega' => $cliente->direccion_entrega,
    ], fn ($valor) => filled($valor));

    $estado = match ($pedido->estado) {
        'COBRADO' => 'Cobrado el '.$pedido->fecha_cobro->format('d/m/Y'),
        'ENTREGADO' => 'Entregado el '.$pedido->fecha_entrega->format('d/m/Y').' · pendiente de cobro',
        default => 'Confirmado el '.$pedido->fecha_confirmacion->format('d/m/Y'),
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pedido N° {{ $pedido->numero() }} · {{ $negocio['nombre'] }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5pt;
            line-height: 1.35;
            color: #141413;
        }
        .franja { background: #141413; color: #ffffff; padding: 9mm 10mm 7mm; }
        .franja table { width: 100%; border-collapse: collapse; }
        .franja td { vertical-align: middle; padding: 0; }
        .logo { height: 13mm; }
        .negocio { font-size: 12pt; font-weight: bold; }
        .titulo { text-align: right; }
        .titulo .tipo { font-size: 8pt; letter-spacing: 0.6pt; text-transform: uppercase; color: #C9C9C3; }
        .titulo .numero { font-size: 15pt; font-weight: bold; margin-top: 1mm; }
        .titulo .fecha { font-size: 8.5pt; color: #C9C9C3; margin-top: 0.5mm; }
        .cuerpo { padding: 6mm 10mm 0; }
        .bloque { width: 100%; border-collapse: collapse; margin-bottom: 5mm; }
        .bloque td { vertical-align: top; padding: 0; }
        .etiqueta { font-size: 7.5pt; letter-spacing: 0.5pt; text-transform: uppercase; color: #5A5A54; margin-bottom: 1mm; }
        .cliente { font-size: 11pt; font-weight: bold; }
        .dato { color: #44443F; font-size: 8.5pt; margin-top: 0.6mm; }
        .estado { text-align: right; font-size: 8.5pt; color: #44443F; }
        .lineas { width: 100%; border-collapse: collapse; }
        .lineas th {
            font-size: 7.5pt; font-weight: bold; letter-spacing: 0.4pt; text-transform: uppercase;
            color: #5A5A54; text-align: left; padding: 0 0 1.6mm; border-bottom: 0.6pt solid #141413;
        }
        .lineas td { padding: 2.2mm 0; border-bottom: 0.4pt solid #E3E3DE; vertical-align: top; }
        .lineas .num { text-align: right; white-space: nowrap; padding-left: 2.5mm; }
        .producto { font-weight: bold; }
        .codigo { font-size: 7.5pt; color: #5A5A54; margin-top: 0.4mm; }
        .totales { width: 58%; margin-left: 42%; border-collapse: collapse; margin-top: 3mm; }
        .totales td { padding: 1.1mm 0; }
        .totales .num { text-align: right; }
        .totales .total td { border-top: 0.8pt solid #141413; padding-top: 2mm; font-size: 12.5pt; font-weight: bold; }
        .observacion { margin-top: 5mm; font-size: 8.5pt; color: #44443F; }
        .pie {
            position: absolute; left: 10mm; right: 10mm; bottom: 8mm;
            border-top: 0.4pt solid #D5D5CF; padding-top: 2.5mm;
            font-size: 7.5pt; color: #5A5A54; text-align: center;
        }
        .pie strong { color: #141413; }
    </style>
</head>
<body>

    <div class="franja">
        <table>
            <tr>
                <td>
                    @if ($logo)
                        <img src="{{ $logo }}" alt="{{ $negocio['nombre'] }}" class="logo">
                    @else
                        <div class="negocio">{{ $negocio['nombre'] }}</div>
                    @endif
                </td>
                <td class="titulo">
                    <div class="tipo">Comprobante de pedido</div>
                    <div class="numero">N° {{ $pedido->numero() }}</div>
                    <div class="fecha">{{ $pedido->fecha_pedido->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="cuerpo">
        <table class="bloque">
            <tr>
                <td>
                    <div class="etiqueta">Cliente</div>
                    <div class="cliente">{{ $cliente->nombre }}</div>
                    @foreach ($datosCliente as $nombre => $valor)
                        <div class="dato">{{ $nombre }}: {{ $valor }}</div>
                    @endforeach
                </td>
                <td class="estado">
                    <div class="etiqueta">Estado</div>
                    {{ $estado }}
                </td>
            </tr>
        </table>

        <table class="lineas">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th class="num">Cantidad</th>
                    <th class="num">Precio</th>
                    <th class="num">Desc.</th>
                    <th class="num">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pedido->lineas as $linea)
                    @php
                        $descuento = $linea->descuento_pct > 0 ? str_replace('.', ',', (string) $linea->descuento_pct).'%' : '—';
                        $retazo = $linea->esCorte() && $linea->descuento_pct == RolloAbierto::DESCUENTO_RETAZO;
                    @endphp
                    <tr>
                        <td>
                            <div class="producto">{{ $linea->producto->nombreCompleto() }}</div>
                            <div class="codigo">
                                {{ $linea->producto->codigo }} · {{ $linea->esCorte() ? 'por metro' : 'pieza entera' }}{{ $retazo ? ' · retazo' : '' }}
                            </div>
                        </td>
                        <td class="num">{{ $linea->cantidadCorta() }}</td>
                        <td class="num">{{ $gs($linea->precio_unitario) }}</td>
                        <td class="num">{{ $descuento }}</td>
                        <td class="num">{{ $gs($linea->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totales">
            <tr><td>Subtotal</td><td class="num">{{ $gs($pedido->subtotal) }}</td></tr>
            <tr><td>Flete</td><td class="num">{{ $gs($pedido->monto_flete) }}</td></tr>
            <tr class="total"><td>Total Gs</td><td class="num">{{ $gs($pedido->total) }}</td></tr>
        </table>

        @if (filled($pedido->observacion))
            <div class="observacion"><strong>Observación:</strong> {{ $pedido->observacion }}</div>
        @endif
    </div>

    <div class="pie">
        <strong>{{ $negocio['nombre'] }}</strong>@if (filled($negocio['contacto'])) · {{ $negocio['contacto'] }}@endif<br>
        Comprobante interno del pedido. Documento sin validez fiscal.
    </div>

</body>
</html>
