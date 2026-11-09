<?php

namespace Database\Seeders;

use App\Exceptions\ErrorDePedido;
use App\Models\Cliente;
use App\Models\Familia;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\PedidoService;
use App\Services\StockService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Datos de demostración: productos, clientes y unos seis meses de pedidos,
 * para ver el Inicio, los reportes y el comprobante con números. Los
 * precios son ficticios, con el costo al 75% del precio (SUP-10).
 *
 * No lo llama DatabaseSeeder. Se carga a mano en una base de desarrollo:
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Los pedidos se hacen con PedidoService, así el stock, los rollos abiertos
 * y los movimientos quedan como si se hubieran cargado de verdad.
 */
class DemoSeeder extends Seeder
{
    private const PRODUCTOS = [
        // codigo, familia, descripcion, unidad, metraje, altura, abertura, calibre, precio, metro, mayorista
        ['HEX-1-100-50', 'Tejido hexagonal', 'Tejido hexagonal de 1" n 18 de 1,00 m x 50 m', 'ROLLO', 50, 100, '1"', 'n 18', 720000, 16500, 690000],
        ['HEX-2-180-50', 'Tejido hexagonal', 'Tejido hexagonal de 2" n 16 de 1,80 m x 50 m', 'ROLLO', 50, 180, '2"', 'n 16', 980000, 22000, null],
        ['TEL-150-25', 'Tejido Tellacor', 'Tejido Tellacor de 1,50 m x 25 m', 'ROLLO', 25, 150, null, null, 310000, 13700, null],
        ['FAP-180-25', 'Tejido Fapol', 'Tejido Fapol de 1,80 m x 25 m', 'ROLLO', 25, 180, null, null, 420000, 18500, null],
        ['SEG-150-25', 'Tejido Seguranca', 'Tejido Seguranca de 1,50 m x 25 m', 'ROLLO', 25, 150, null, null, 380000, 16800, null],
        ['ALA-16-KG', 'Alambres y cordones', 'Alambre galvanizado n 16 por kilo', 'KILO', null, null, null, 'n 16', 18000, null, null],
        ['ALA-500', 'Alambres y cordones', 'Alambre de púa de 500 m', 'ROLLO', 500, null, null, null, 285000, null, 270000],
        ['GRA-240', 'Gradiles, postes y accesorios', 'Poste de hormigón de 2,40 m', 'UNIDAD', null, 240, null, null, 45000, null, null],
        ['GRA-TENSOR', 'Gradiles, postes y accesorios', 'Tensor para tejido', 'UNIDAD', null, null, null, null, 12000, null, null],
    ];

    private const CLIENTES = [
        ['Nicolasa Benítez', '3456789', '0981 234 567', 'Salto del Guairá, barrio San Miguel'],
        ['Ramón Caballero', '2876543', '0983 456 120', 'Katueté, ruta 10 km 3'],
        ['Estanislao González', '4123987', '0975 880 214', 'La Paloma, calle Itá 120'],
        ['Zulma Ortiz', '5098123', '0982 771 905', 'Salto del Guairá, centro'],
        ['Agroganadera San Pedro', '80045123-7', '0461 242 310', 'Corpus Christi, ruta 10'],
        ['Liliana Duarte', '3987001', '0971 556 432', 'Nueva Esperanza'],
        ['Hugo Villalba', '4677120', '0984 300 818', 'Salto del Guairá, barrio Pindoty'],
        ['Mirta Acosta', '2550998', '0986 412 077', 'Francisco Caballero Álvarez'],
    ];

    /** Hoy de verdad: durante la carga se mueve el reloj con Carbon::setTestNow(). */
    private CarbonImmutable $hoy;

    public function run(PedidoService $pedidos, StockService $stock): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Los datos de demostración no se cargan en producción.');
        }

        mt_srand(2026);

        $hoy = $this->hoy = CarbonImmutable::now();
        $inicio = $hoy->startOfMonth()->subMonths(5);

        $productos = $this->productos();
        $clientes = $this->clientes();

        // Carga inicial de mercadería, antes del primer pedido.
        Carbon::setTestNow($inicio->subDays(3)->setTime(9, 0));
        foreach ($productos as $producto) {
            $piezas = match ($producto->unidad_venta) {
                'KILO' => 400,
                'UNIDAD' => 300,
                default => 30,
            };
            $stock->ingresar($producto, $piezas, $producto->movimientos()->exists() ? 'REPOSICION' : 'CARGA_INICIAL', 'Datos de demostración');
        }

        // Pedidos cobrados, unos por semana, con más movimiento en los meses de más calor.
        for ($dia = $inicio; $dia->lessThan($hoy->subDays(4)); $dia = $dia->addDays(mt_rand(2, 5))) {
            $pedido = $this->pedido($pedidos, $clientes, $productos, $dia->setTime(mt_rand(8, 18), mt_rand(0, 59)));

            if ($pedido) {
                $this->avanzar($pedidos, $pedido, $dia, 'COBRADO');
            }
        }

        // Reposición a mitad de camino, para que no falte stock.
        Carbon::setTestNow($inicio->addMonths(3)->setTime(9, 0));
        foreach ($productos as $producto) {
            $stock->ingresar($producto, $producto->unidad_venta === 'ROLLO' ? 10 : 100, 'REPOSICION', 'Datos de demostración');
        }

        // Pedidos abiertos de estos días: por cobrar, por entregar y en borrador.
        foreach ([[6, 'ENTREGADO'], [3, 'ENTREGADO'], [2, 'CONFIRMADO'], [1, 'CONFIRMADO'], [1, 'BORRADOR'], [0, 'BORRADOR']] as [$haceDias, $estado]) {
            $dia = $hoy->subDays($haceDias)->setTime(mt_rand(8, 11), mt_rand(0, 59))->min($hoy->subMinutes(30));
            $pedido = $this->pedido($pedidos, $clientes, $productos, $dia);

            if ($pedido) {
                $this->avanzar($pedidos, $pedido, $dia, $estado);
            }
        }

        Carbon::setTestNow();
    }

    /**
     * Un pedido en borrador con entre una y tres líneas al azar.
     */
    private function pedido(PedidoService $pedidos, array $clientes, array $productos, CarbonImmutable $fecha): ?Pedido
    {
        Carbon::setTestNow($fecha);

        $lineas = [];
        $elegidos = (array) array_rand($productos, mt_rand(1, 3));

        foreach ($elegidos as $indice) {
            $producto = $productos[$indice];
            $corte = $producto->seCorta() && mt_rand(1, 100) <= 55;

            $lineas[] = [
                'id_producto' => $producto->id_producto,
                'tipo_venta' => $corte ? 'METRO' : $producto->unidad_venta,
                'cantidad' => match (true) {
                    $corte => mt_rand(3, 30),
                    $producto->unidad_venta === 'KILO' => mt_rand(5, 25),
                    $producto->unidad_venta === 'UNIDAD' => mt_rand(4, 30),
                    default => mt_rand(1, 3),
                },
                'precio_unitario' => $corte ? $producto->precio_metro : $producto->precio_venta,
                'descuento_pct' => mt_rand(1, 100) <= 25 ? mt_rand(1, 5) : 0,
            ];
        }

        $flete = mt_rand(1, 100) <= 70 ? [60000, 80000, 100000, 120000, 150000][mt_rand(0, 4)] : 0;

        try {
            return $pedidos->guardar(null, $clientes[array_rand($clientes)]->id_cliente, $lineas, $flete);
        } catch (ErrorDePedido) {
            return null;
        }
    }

    /**
     * Lleva el pedido hasta el estado pedido, con fechas creíbles: se
     * confirma en el día, se entrega a los uno o dos días y se cobra al
     * entregar o unos días después.
     */
    private function avanzar(PedidoService $pedidos, Pedido $pedido, CarbonImmutable $fecha, string $hasta): void
    {
        if ($hasta === 'BORRADOR') {
            return;
        }

        try {
            Carbon::setTestNow($fecha->addMinutes(mt_rand(5, 90)));
            $pedidos->confirmar($pedido);

            // Los movimientos toman la fecha de la base: se alinean con la confirmación.
            DB::table('movimiento_stock')->where('id_pedido', $pedido->id_pedido)->update(['fecha' => now()]);

            if ($hasta === 'CONFIRMADO') {
                return;
            }

            // Nunca después de hoy, y siempre después de confirmar.
            $confirmado = CarbonImmutable::now();
            $entrega = $fecha->addDays(mt_rand(0, 2))->setTime(mt_rand(9, 17), mt_rand(0, 59))
                ->min($this->hoy)->max($confirmado->addMinutes(10));
            Carbon::setTestNow($entrega);
            $pedidos->marcarEntregado($pedido);

            if ($hasta === 'ENTREGADO') {
                return;
            }

            Carbon::setTestNow($entrega->addDays(mt_rand(0, 3))->addHours(1)->min($this->hoy)->max($entrega->addMinutes(5)));
            $pedidos->registrarCobro($pedido);
        } catch (ErrorDePedido) {
            // Sin stock para este pedido: queda en borrador.
        }
    }

    /** @return array<int, Producto> */
    private function productos(): array
    {
        $familias = Familia::pluck('id_familia', 'nombre');

        $nuevos = collect(self::PRODUCTOS)->map(fn (array $datos) => Producto::firstOrCreate(['codigo' => $datos[0]], [
            'id_familia' => $familias[$datos[1]],
            'descripcion' => $datos[2],
            'unidad_venta' => $datos[3],
            'fraccionable' => $datos[9] ? 'S' : 'N',
            'metraje_rollo' => $datos[4],
            'altura_cm' => $datos[5],
            'abertura' => $datos[6],
            'calibre' => $datos[7],
            'precio_venta' => $datos[8],
            'precio_metro' => $datos[9],
            'precio_mayorista' => $datos[10],
            'costo' => (int) round($datos[8] * 0.75),
            'activo' => 'S',
        ]));

        // Los dos productos de ejemplo del script v1.1 también se venden.
        return Producto::activos()->whereIn('codigo', ['HEX-12-150-50', 'ROM-5-150-25'])->get()
            ->merge($nuevos)
            ->values()
            ->all();
    }

    /** @return array<int, Cliente> */
    private function clientes(): array
    {
        return collect(self::CLIENTES)->map(fn (array $datos) => Cliente::firstOrCreate(['nombre' => $datos[0]], [
            'documento' => $datos[1],
            'telefono' => $datos[2],
            'direccion_entrega' => $datos[3],
            'activo' => 'S',
        ]))->all();
    }
}
