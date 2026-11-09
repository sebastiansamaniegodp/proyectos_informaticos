<?php

namespace App\Services;

use App\Models\Familia;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\RolloAbierto;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reportes: pedidos pendientes y resultado del mes (PR-05).
 *
 * El resultado del mes toma solo los pedidos COBRADOS, por su fecha de
 * cobro (SP-07). Las ventas son la suma de las líneas, sin el flete, que se
 * informa aparte (RN-32, RN-33). El costo de cada línea es la cantidad por
 * el costo unitario que se copió al confirmar (RN-34): editar el costo de un
 * producto no cambia meses ya cerrados. Costo y ganancia van en guaraníes
 * enteros, sin el redondeo a 500 de los importes de venta (SP-02).
 */
class ReporteService
{
    /** Costo de una línea en SQL: cantidad × costo copiado (RN-33). */
    private const COSTO_LINEA = 'ROUND(d.cantidad * COALESCE(d.costo_unitario, 0))';

    /**
     * Ventas, costo, ganancia y flete del mes, con la lista de pedidos
     * cobrados.
     */
    public function resultadoDelMes(CarbonInterface $mes): object
    {
        [$desde, $hasta] = $this->limites($mes);

        $pedidos = DB::table('pedido as p')
            ->join('cliente as c', 'c.id_cliente', '=', 'p.id_cliente')
            ->where('p.estado', 'COBRADO')
            ->where('p.fecha_cobro', '>=', $desde)
            ->where('p.fecha_cobro', '<', $hasta)
            ->select('p.id_pedido', 'p.fecha_cobro', 'p.subtotal', 'p.monto_flete', 'p.total', 'c.nombre as cliente')
            ->selectSub(fn (Builder $query) => $query->from('pedido_detalle as d')
                ->whereColumn('d.id_pedido', 'p.id_pedido')
                ->selectRaw('COALESCE(SUM('.self::COSTO_LINEA.'), 0)'), 'costo')
            ->selectSub(fn (Builder $query) => $query->from('pedido_detalle as d')
                ->whereColumn('d.id_pedido', 'p.id_pedido')
                ->whereNull('d.costo_unitario')
                ->selectRaw('COUNT(*)'), 'lineas_sin_costo')
            ->orderBy('p.fecha_cobro')
            ->orderBy('p.id_pedido')
            ->get()
            ->map(function (object $pedido) {
                $pedido->subtotal = (int) $pedido->subtotal;
                $pedido->monto_flete = (int) $pedido->monto_flete;
                $pedido->costo = (int) $pedido->costo;
                $pedido->ganancia = $pedido->subtotal - $pedido->costo;
                $pedido->fecha_cobro = CarbonImmutable::parse($pedido->fecha_cobro);
                $pedido->numero = Pedido::formatearNumero($pedido->id_pedido);

                return $pedido;
            });

        $ventas = $pedidos->sum('subtotal');
        $costo = $pedidos->sum('costo');

        return (object) [
            'mes' => CarbonImmutable::parse($desde),
            'ventas' => $ventas,
            'costo' => $costo,
            'ganancia' => $ventas - $costo,
            'margen' => $ventas > 0 ? ($ventas - $costo) / $ventas * 100 : null,
            'flete' => $pedidos->sum('monto_flete'),
            'pedidos' => $pedidos,
            'lineas_sin_costo' => (int) $pedidos->sum('lineas_sin_costo'),
        ];
    }

    /**
     * Ventas, costo y ganancia de cada uno de los últimos meses, del más
     * viejo al actual. Los meses sin cobros van en cero.
     *
     * @return Collection<int, object{mes: CarbonImmutable, ventas: int, costo: int, ganancia: int, pedidos: int}>
     */
    public function resultadoPorMes(CarbonInterface $hasta, int $meses = 6): Collection
    {
        $ultimo = CarbonImmutable::parse($hasta)->startOfMonth();
        $primero = $ultimo->subMonths($meses - 1);

        $filas = DB::table('pedido as p')
            ->where('p.estado', 'COBRADO')
            ->where('p.fecha_cobro', '>=', $primero)
            ->where('p.fecha_cobro', '<', $ultimo->addMonth())
            ->selectRaw("to_char(p.fecha_cobro, 'YYYY-MM') as mes")
            ->selectRaw('SUM(p.subtotal) as ventas')
            ->selectRaw('COUNT(*) as pedidos')
            ->selectRaw('SUM((SELECT COALESCE(SUM('.self::COSTO_LINEA.'), 0) FROM pedido_detalle d WHERE d.id_pedido = p.id_pedido)) as costo')
            ->groupByRaw("to_char(p.fecha_cobro, 'YYYY-MM')")
            ->get()
            ->keyBy('mes');

        return collect(range(0, $meses - 1))->map(function (int $desplazamiento) use ($primero, $filas) {
            $mes = $primero->addMonths($desplazamiento);
            $fila = $filas->get($mes->format('Y-m'));
            $ventas = (int) ($fila->ventas ?? 0);
            $costo = (int) ($fila->costo ?? 0);

            return (object) [
                'mes' => $mes,
                'ventas' => $ventas,
                'costo' => $costo,
                'ganancia' => $ventas - $costo,
                'pedidos' => (int) ($fila->pedidos ?? 0),
            ];
        });
    }

    /**
     * Ventas, costo y ganancia por familia de productos, de los pedidos
     * cobrados entre las dos fechas. De la que más vendió a la que menos.
     */
    public function ventasPorFamilia(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        return DB::table('pedido_detalle as d')
            ->join('pedido as p', 'p.id_pedido', '=', 'd.id_pedido')
            ->join('producto as pr', 'pr.id_producto', '=', 'd.id_producto')
            ->join('familia as f', 'f.id_familia', '=', 'pr.id_familia')
            ->where('p.estado', 'COBRADO')
            ->where('p.fecha_cobro', '>=', $desde)
            ->where('p.fecha_cobro', '<', $hasta)
            ->groupBy('f.id_familia', 'f.nombre')
            ->select('f.id_familia', 'f.nombre')
            ->selectRaw('SUM(d.subtotal) as ventas')
            ->selectRaw('SUM('.self::COSTO_LINEA.') as costo')
            ->orderByDesc('ventas')
            ->get()
            ->map(function (object $fila) {
                $fila->ventas = (int) $fila->ventas;
                $fila->costo = (int) $fila->costo;
                $fila->ganancia = $fila->ventas - $fila->costo;
                $fila->nombre_corto = (new Familia(['nombre' => $fila->nombre]))->nombreCorto();

                return $fila;
            });
    }

    /**
     * Los productos que más vendieron entre las dos fechas, por importe.
     */
    public function masVendidos(CarbonInterface $desde, CarbonInterface $hasta, int $cantidad = 5): Collection
    {
        $filas = DB::table('pedido_detalle as d')
            ->join('pedido as p', 'p.id_pedido', '=', 'd.id_pedido')
            ->where('p.estado', 'COBRADO')
            ->where('p.fecha_cobro', '>=', $desde)
            ->where('p.fecha_cobro', '<', $hasta)
            ->groupBy('d.id_producto')
            ->select('d.id_producto')
            ->selectRaw('SUM(d.subtotal) as ventas')
            ->selectRaw("SUM(CASE WHEN d.tipo_venta = 'METRO' THEN d.cantidad ELSE 0 END) as metros")
            ->selectRaw("SUM(CASE WHEN d.tipo_venta <> 'METRO' THEN d.cantidad ELSE 0 END) as piezas")
            ->orderByDesc('ventas')
            ->limit($cantidad)
            ->get();

        $productos = Producto::with('familia')->whereIn('id_producto', $filas->pluck('id_producto'))->get()->keyBy('id_producto');

        return $filas->map(fn (object $fila) => (object) [
            'producto' => $productos[$fila->id_producto],
            'ventas' => (int) $fila->ventas,
            'metros' => (float) $fila->metros,
            'piezas' => (float) $fila->piezas,
        ]);
    }

    /**
     * Pedidos por confirmar o sin entregar (RN-26, REP-07), del más viejo al
     * más nuevo: el que más espera va primero.
     */
    public function pedidosPendientes(?int $limite = null): Collection
    {
        return Pedido::with(['cliente', 'lineas.producto.familia'])
            ->enEstado(Pedido::PENDIENTES)
            ->orderBy('fecha_pedido')
            ->orderBy('id_pedido')
            ->when($limite, fn ($query) => $query->limit($limite))
            ->get();
    }

    /**
     * Cantidad y total de los pedidos en cada estado abierto: borrador,
     * confirmado (falta entregar) y entregado (falta cobrar).
     */
    public function pedidosAbiertos(): Collection
    {
        $filas = Pedido::query()
            ->enEstado(['BORRADOR', 'CONFIRMADO', 'ENTREGADO'])
            ->groupBy('estado')
            ->selectRaw('estado, COUNT(*) as cantidad, SUM(total) as total')
            ->get()
            ->keyBy('estado');

        return collect(['BORRADOR', 'CONFIRMADO', 'ENTREGADO'])->mapWithKeys(fn (string $estado) => [$estado => (object) [
            'cantidad' => (int) ($filas[$estado]->cantidad ?? 0),
            'total' => (int) ($filas[$estado]->total ?? 0),
        ]]);
    }

    /**
     * Pedidos entregados que todavía no se cobraron, del más viejo al más
     * nuevo.
     */
    public function porCobrar(?int $limite = null): Collection
    {
        return Pedido::with(['cliente', 'lineas.producto.familia'])
            ->enEstado('ENTREGADO')
            ->orderBy('fecha_entrega')
            ->when($limite, fn ($query) => $query->limit($limite))
            ->get();
    }

    /**
     * Rollos abiertos de los productos activos, de los que menos metros les
     * quedan a los que más: arriba los retazos (RN-12).
     */
    public function rollosAbiertos(?int $limite = null): Collection
    {
        return RolloAbierto::with('producto.familia')
            ->abiertos()
            ->whereHas('producto', fn ($query) => $query->activos())
            ->orderBy('metros_restantes')
            ->orderBy('id_rollo')
            ->when($limite, fn ($query) => $query->limit($limite))
            ->get();
    }

    /** Primer instante del mes y primer instante del mes siguiente. */
    private function limites(CarbonInterface $mes): array
    {
        $desde = CarbonImmutable::parse($mes)->startOfMonth();

        return [$desde, $desde->addMonth()];
    }
}
