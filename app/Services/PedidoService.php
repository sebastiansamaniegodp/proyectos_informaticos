<?php

namespace App\Services;

use App\Exceptions\ErrorDePedido;
use App\Exceptions\ErrorDeStock;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\RolloAbierto;
use App\Models\Stock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Único lugar que escribe PEDIDO y PEDIDO_DETALLE (arquitectura, punto 6).
 *
 * Estados y transiciones (DD-06, RN-26, RN-27):
 *
 *   BORRADOR   → CONFIRMADO  descuenta el stock (RN-13)
 *   BORRADOR   → ANULADO     no toca el stock
 *   CONFIRMADO → ENTREGADO   registra la fecha de entrega
 *   CONFIRMADO → ANULADO     repone el stock (RN-14)
 *   ENTREGADO  → COBRADO     registra la fecha de cobro
 *   ENTREGADO  → ANULADO     repone el stock; si la mercadería no vuelve,
 *                            se registra un ajuste a mano (tabla de RN-27)
 *   COBRADO y ANULADO son finales.
 *
 * El stock no se toca desde acá: se le pide a StockService, que decide de
 * qué rollo sale cada corte. Cada operación corre en una transacción que
 * empieza bloqueando la fila del pedido: un doble toque en "Confirmar" con
 * una conexión lenta espera, encuentra el pedido ya confirmado y se rechaza.
 */
class PedidoService
{
    /** Descuento manual máximo por línea (RN-22). */
    public const DESCUENTO_MAXIMO = 5;

    public function __construct(private StockService $stock) {}

    /**
     * Crea un pedido en borrador o reemplaza las líneas de uno que todavía
     * está en borrador (RN-27).
     *
     * Cada línea es un array con id_producto, tipo_venta (METRO para un
     * corte, o la unidad de venta del producto), cantidad, precio_unitario
     * y descuento_pct. El precio tiene que ser uno de los del producto, o el
     * que la línea ya tenía guardado: así un cambio de precio posterior no
     * altera el pedido (RN-19).
     *
     * @param  array<int, array{id_producto:int, tipo_venta:string, cantidad:int|float|string, precio_unitario:int, descuento_pct?:int|float|string}>  $lineas
     */
    public function guardar(?Pedido $pedido, int $idCliente, array $lineas, int $flete, ?string $observacion = null): Pedido
    {
        if ($lineas === []) {
            throw new ErrorDePedido('Agregá al menos un producto al pedido.');
        }

        if ($flete < 0) {
            throw new ErrorDePedido('El flete no puede ser negativo. Si no se cobra flete, dejalo en 0.');
        }

        if ($observacion !== null && mb_strlen($observacion) > 400) {
            throw new ErrorDePedido('La observación puede tener hasta 400 letras.');
        }

        return DB::transaction(function () use ($pedido, $idCliente, $lineas, $flete, $observacion) {
            $previas = collect();

            if ($pedido?->exists) {
                $pedido = $this->bloquear($pedido);
                $this->exigirBorrador($pedido);
                $previas = PedidoDetalle::where('id_pedido', $pedido->id_pedido)->get();
            } else {
                $pedido = new Pedido(['estado' => 'BORRADOR']);
                $pedido->fecha_pedido = now();
            }

            $cliente = Cliente::find($idCliente);

            if (! $cliente || ($cliente->activo !== 'S' && $cliente->id_cliente !== $pedido->id_cliente)) {
                throw new ErrorDePedido('Elegí el cliente del pedido.');
            }

            $filas = [];

            foreach (array_values($lineas) as $indice => $linea) {
                $filas[] = $this->armarLinea($linea, $indice + 1, $previas);
            }

            $subtotal = array_sum(array_column($filas, 'subtotal'));

            $pedido->fill([
                'id_cliente' => $cliente->id_cliente,
                'monto_flete' => $flete,
                'subtotal' => $subtotal,
                'total' => $subtotal + $flete,
                'observacion' => $observacion,
            ])->save();

            DB::table('pedido_detalle')->where('id_pedido', $pedido->id_pedido)->delete();
            DB::table('pedido_detalle')->insert(array_map(
                fn (array $fila) => $fila + ['id_pedido' => $pedido->id_pedido],
                $filas,
            ));

            return $pedido->refresh();
        });
    }

    /**
     * Confirma el pedido y descuenta el stock línea por línea, en orden
     * (RN-13, RN-28). Si una línea no tiene stock no se descuenta nada del
     * pedido (SP-04) y el mensaje dice cuál fue.
     *
     * El costo del producto se copia a cada línea en este momento (RN-34),
     * y en los cortes también el rollo del que salieron (RN-15).
     */
    public function confirmar(Pedido $pedido): Pedido
    {
        return DB::transaction(function () use ($pedido) {
            $pedido = $this->bloquear($pedido);

            if ($pedido->estado !== 'BORRADOR') {
                throw new ErrorDePedido($pedido->estado === 'CONFIRMADO'
                    ? 'Este pedido ya está confirmado.'
                    : 'Solo un pedido en borrador se puede confirmar.');
            }

            $lineas = $pedido->lineas()->with('producto.familia')->get();

            if ($lineas->isEmpty()) {
                throw new ErrorDePedido('Agregá al menos un producto antes de confirmar.');
            }

            foreach ($lineas as $linea) {
                try {
                    $movimiento = $linea->esCorte()
                        ? $this->stock->venderMetros($linea->producto, $linea->cantidad, $pedido->id_pedido)
                        : $this->stock->venderPiezas($linea->producto, (int) $linea->cantidad, $pedido->id_pedido);
                } catch (ErrorDeStock $error) {
                    throw new ErrorDePedido(
                        "No se confirmó: falta stock en la línea {$linea->nro_linea} ({$linea->producto->nombreCompleto()}). {$error->getMessage()}",
                        previous: $error,
                    );
                }

                DB::table('pedido_detalle')
                    ->where('id_pedido', $pedido->id_pedido)
                    ->where('nro_linea', $linea->nro_linea)
                    ->update([
                        'id_rollo' => $movimiento->id_rollo,
                        'costo_unitario' => $linea->esCorte() ? $linea->producto->costoPorMetro() : $linea->producto->costo,
                    ]);
            }

            $pedido->estado = 'CONFIRMADO';
            $pedido->fecha_confirmacion = now();
            $pedido->save();

            return $pedido->refresh();
        });
    }

    public function marcarEntregado(Pedido $pedido): Pedido
    {
        return DB::transaction(function () use ($pedido) {
            $pedido = $this->bloquear($pedido);

            if ($pedido->estado !== 'CONFIRMADO') {
                throw new ErrorDePedido(match ($pedido->estado) {
                    'BORRADOR' => 'Primero confirmá el pedido: así se descuenta el stock.',
                    'ENTREGADO' => 'Este pedido ya está entregado.',
                    default => 'Un pedido '.mb_strtolower($pedido->nombreEstado()).' no se puede marcar como entregado.',
                });
            }

            $pedido->estado = 'ENTREGADO';
            $pedido->fecha_entrega = now();
            $pedido->save();

            return $pedido->refresh();
        });
    }

    public function registrarCobro(Pedido $pedido): Pedido
    {
        return DB::transaction(function () use ($pedido) {
            $pedido = $this->bloquear($pedido);

            if ($pedido->estado !== 'ENTREGADO') {
                throw new ErrorDePedido(match ($pedido->estado) {
                    'BORRADOR' => 'Primero confirmá el pedido y marcalo como entregado.',
                    'CONFIRMADO' => 'Primero marcá el pedido como entregado.',
                    'COBRADO' => 'Este pedido ya está cobrado.',
                    default => 'Un pedido anulado no se puede cobrar.',
                });
            }

            $pedido->estado = 'COBRADO';
            $pedido->fecha_cobro = now();
            $pedido->save();

            return $pedido->refresh();
        });
    }

    /**
     * Anula el pedido. Si ya había descontado stock, lo repone: las piezas
     * vuelven a las cerradas y los metros a su rollo de origen (RN-14, SP-05).
     */
    public function anular(Pedido $pedido): Pedido
    {
        return DB::transaction(function () use ($pedido) {
            $pedido = $this->bloquear($pedido);

            if (in_array($pedido->estado, ['COBRADO', 'ANULADO'], true)) {
                throw new ErrorDePedido($pedido->estado === 'COBRADO'
                    ? 'Un pedido cobrado ya no se puede anular.'
                    : 'Este pedido ya está anulado.');
            }

            if ($pedido->estado !== 'BORRADOR') {
                $this->stock->reponerPedido($pedido->id_pedido);
            }

            $pedido->estado = 'ANULADO';
            $pedido->save();

            return $pedido->refresh();
        });
    }

    /**
     * Valida una línea y calcula su importe (RN-01 a RN-05, RN-18 a RN-24).
     * Devuelve la fila lista para PEDIDO_DETALLE, sin el id del pedido. La
     * pantalla la usa para revisar una línea antes de agregarla.
     *
     * @param  Collection<int, PedidoDetalle>|null  $previas  líneas ya guardadas del pedido
     */
    public function armarLinea(array $linea, int $nro = 1, ?Collection $previas = null): array
    {
        $previas ??= collect();
        $producto = Producto::with('familia')->find($linea['id_producto'] ?? null);

        if (! $producto) {
            throw new ErrorDePedido("La línea {$nro} no tiene producto.");
        }

        $nombre = $producto->nombreCompleto();
        $tipo = (string) ($linea['tipo_venta'] ?? '');
        $yaEstaba = $previas->contains(fn (PedidoDetalle $previa) => $previa->id_producto === $producto->id_producto);

        if ($producto->activo !== 'S' && ! $yaEstaba) {
            throw new ErrorDePedido("{$nombre} está dado de baja.");
        }

        if ($tipo === 'METRO' && ! $producto->seCorta()) {
            throw new ErrorDePedido("{$nombre} no se corta: se vende solo por pieza entera.");
        }

        if ($tipo !== 'METRO' && $tipo !== $producto->unidad_venta) {
            throw new ErrorDePedido("Elegí cómo se vende {$nombre}.");
        }

        $cantidad = self::numero($linea['cantidad'] ?? null);

        if ($cantidad === null || $cantidad <= 0 || $cantidad > 99999) {
            throw new ErrorDePedido("La cantidad de {$nombre} tiene que ser mayor a cero.");
        }

        if ($tipo === 'METRO') {
            if (round($cantidad, 2) != $cantidad) {
                throw new ErrorDePedido('Los metros van con hasta dos decimales, por ejemplo 7,5.');
            }

            // Un corte sale de un solo rollo (RN-05).
            if ($cantidad > $producto->metraje_rollo) {
                throw new ErrorDePedido("Un corte sale de un solo rollo, y el rollo trae {$producto->metraje_rollo} m. Para más metros, vendé rollos enteros.");
            }
        } elseif (floor($cantidad) != $cantidad) {
            throw new ErrorDePedido('Los '.$producto->nombrePiezas().' se venden enteros: escribí la cantidad sin decimales.');
        }

        $descuento = self::numero($linea['descuento_pct'] ?? 0) ?? -1;
        $retazo = $tipo === 'METRO' && $descuento == RolloAbierto::DESCUENTO_RETAZO;

        // Hasta 5% a mano (RN-22), o el 20% del retazo, que no se acumula
        // con el manual (RN-23, SP-06).
        if (! $retazo && ($descuento < 0 || $descuento > self::DESCUENTO_MAXIMO || round($descuento, 2) != $descuento)) {
            throw new ErrorDePedido('El descuento va de 0 a '.self::DESCUENTO_MAXIMO.'%, que es el máximo autorizado.');
        }

        $precio = (int) ($linea['precio_unitario'] ?? 0);
        $permitidos = $tipo === 'METRO'
            ? [$producto->precio_metro]
            : [$producto->precio_venta, $producto->precio_mayorista];
        $guardados = $previas
            ->filter(fn (PedidoDetalle $previa) => $previa->id_producto === $producto->id_producto && $previa->tipo_venta === $tipo)
            ->pluck('precio_unitario')
            ->all();

        if ($precio <= 0 || ! in_array($precio, array_merge($permitidos, $guardados), true)) {
            throw new ErrorDePedido("El precio de {$nombre} no es uno de los del producto. Volvé a cargar la línea.");
        }

        return [
            'nro_linea' => $nro,
            'id_producto' => $producto->id_producto,
            'tipo_venta' => $tipo,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'descuento_pct' => $descuento,
            'subtotal' => Redondeo::importeLinea($cantidad, $precio, $descuento),
        ];
    }

    private function bloquear(Pedido $pedido): Pedido
    {
        return Pedido::lockForUpdate()->findOrFail($pedido->id_pedido);
    }

    private function exigirBorrador(Pedido $pedido): void
    {
        if (! $pedido->esBorrador()) {
            throw new ErrorDePedido('Solo un pedido en borrador se puede cambiar. Para corregir uno confirmado, anulalo y cargalo de nuevo.');
        }
    }

    /**
     * Número escrito en pantalla, con coma o punto decimal: "7,5" da 7.5.
     * Devuelve null si no es un número.
     */
    public static function numero(int|float|string|null $valor): ?float
    {
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $valor = str_replace(',', '.', trim((string) $valor));

        return preg_match('/^\d+(\.\d+)?$/', $valor) ? (float) $valor : null;
    }

    /**
     * Texto del stock disponible de un producto, para la pantalla de agregar
     * línea: "2 rollos cerrados y 43 m sueltos".
     */
    public static function disponible(Producto $producto): string
    {
        $fila = DB::table('vw_stock_disponible')->where('id_producto', $producto->id_producto)->first();
        $cerradas = (int) ($fila->piezas_cerradas ?? 0);

        if (! $producto->seCorta()) {
            return $cerradas > 0 ? Producto::cantidadConUnidad($cerradas, $producto->unidad_venta) : 'Sin stock';
        }

        $sueltos = (float) ($fila->metros_sueltos ?? 0);

        if ($cerradas === 0 && $sueltos == 0) {
            return 'Sin stock';
        }

        $texto = Producto::cantidadConUnidad($cerradas, $producto->unidad_venta).' '.($cerradas == 1 ? 'cerrado' : 'cerrados');

        return $sueltos > 0 ? $texto.' y '.Stock::formatearCantidad($sueltos).' m sueltos' : $texto;
    }
}
