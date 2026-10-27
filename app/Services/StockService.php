<?php

namespace App\Services;

use App\Exceptions\ErrorDeStock;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\RolloAbierto;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

/**
 * Único lugar que escribe el stock: STOCK, ROLLO_ABIERTO y MOVIMIENTO_STOCK.
 *
 * Cada operación corre en una transacción y empieza bloqueando la fila de
 * STOCK del producto con SELECT ... FOR UPDATE. Así dos operaciones sobre el
 * mismo producto se hacen una detrás de la otra, y nunca se corta dos veces
 * el mismo rollo ni se vende dos veces la última pieza.
 *
 * Las cantidades en metros admiten dos decimales; las piezas son enteras.
 * Lo que no se puede hacer termina en ErrorDeStock con un mensaje para la
 * pantalla, y la transacción no deja nada a medias.
 */
class StockService
{
    /**
     * Entrada de mercadería: piezas cerradas nuevas (RN-35). Es la única
     * forma de aumentar el stock, porque compras quedó fuera del alcance.
     */
    public function ingresar(Producto $producto, int $piezas, string $motivo, ?string $observacion = null): MovimientoStock
    {
        if (! array_key_exists($motivo, MovimientoStock::MOTIVOS_INGRESO)) {
            throw new ErrorDeStock('Elegí si es la carga inicial o una reposición.');
        }

        if ($piezas <= 0) {
            throw new ErrorDeStock('La cantidad que ingresa tiene que ser mayor a cero.');
        }

        return DB::transaction(function () use ($producto, $piezas, $motivo, $observacion) {
            $stock = $this->bloquear($producto);
            $this->sumarPiezas($stock, $piezas);

            return $this->registrar($producto, 'INGRESO', $motivo, $piezas, $producto->unidad_venta, observacion: $observacion);
        });
    }

    /**
     * Ajuste de piezas cerradas (DD-07, RN-30). Negativo sale; positivo solo
     * con motivo INVENTARIO, cuando el conteo da más de lo registrado.
     */
    public function ajustarPiezas(Producto $producto, int $cantidad, string $motivo, ?string $observacion = null): MovimientoStock
    {
        $this->validarAjuste($cantidad, $motivo);

        return DB::transaction(function () use ($producto, $cantidad, $motivo, $observacion) {
            $stock = $this->bloquear($producto);

            if ($cantidad < 0) {
                $this->exigirPiezas($producto, $stock, -$cantidad);
            }

            $this->sumarPiezas($stock, $cantidad);

            return $this->registrar($producto, 'AJUSTE', $motivo, $cantidad, $producto->unidad_venta, observacion: $observacion);
        });
    }

    /**
     * Ajuste en metros de un producto que se corta (DD-07, RN-30).
     *
     * Si se indica el rollo, el ajuste va a ese rollo. Si no, una salida se
     * corta del rollo que tocaría en una venta (RN-09, RN-10). Para sumar
     * metros (diferencia de inventario) hay que indicar el rollo.
     */
    public function ajustarMetros(Producto $producto, float $metros, string $motivo, ?RolloAbierto $rollo = null, ?string $observacion = null): MovimientoStock
    {
        $metros = round($metros, 2);
        $this->exigirCorte($producto);
        $this->validarAjuste($metros, $motivo);

        if ($metros > 0 && ! $rollo) {
            throw new ErrorDeStock('Elegí a qué rollo se le suman los metros.');
        }

        return DB::transaction(function () use ($producto, $metros, $motivo, $rollo, $observacion) {
            $stock = $this->bloquear($producto);

            if ($rollo) {
                $rollo = RolloAbierto::where('id_producto', $producto->id_producto)
                    ->abiertos()
                    ->lockForUpdate()
                    ->find($rollo->id_rollo)
                    ?? throw new ErrorDeStock('Ese rollo ya no está abierto.');

                if ($rollo->metros_restantes + $metros < 0) {
                    throw new ErrorDeStock("Al rollo #{$rollo->id_rollo} le quedan ".Stock::formatearCantidad($rollo->metros_restantes).' m.');
                }

                if ($rollo->metros_restantes + $metros > $rollo->metros_iniciales) {
                    throw new ErrorDeStock("El rollo #{$rollo->id_rollo} no puede tener más de los ".Stock::formatearCantidad($rollo->metros_iniciales).' m con que empezó.');
                }
            } else {
                $rollo = $this->rolloParaCortar($producto, $stock, -$metros);
            }

            $this->moverMetros($rollo, $metros);

            return $this->registrar($producto, 'AJUSTE', $motivo, $metros, 'METRO', $rollo, observacion: $observacion);
        });
    }

    /**
     * Salida por venta de piezas enteras, al confirmar un pedido (RN-08,
     * RN-13). Una pieza entera sale siempre de las cerradas: un rollo ya
     * cortado no se vende como rollo entero, aunque los metros alcancen.
     */
    public function venderPiezas(Producto $producto, int $cantidad, int $idPedido): MovimientoStock
    {
        if ($cantidad <= 0) {
            throw new ErrorDeStock('La cantidad vendida tiene que ser mayor a cero.');
        }

        return DB::transaction(function () use ($producto, $cantidad, $idPedido) {
            $stock = $this->bloquear($producto);
            $this->exigirPiezas($producto, $stock, $cantidad);
            $this->sumarPiezas($stock, -$cantidad);

            return $this->registrar($producto, 'VENTA', null, -$cantidad, $producto->unidad_venta, idPedido: $idPedido);
        });
    }

    /**
     * Salida por venta de metros cortados, al confirmar un pedido (RN-02,
     * RN-05, RN-09 a RN-11, RN-13). El movimiento devuelto trae id_rollo:
     * es el rollo del que se cortó, que va a PEDIDO_DETALLE.id_rollo.
     */
    public function venderMetros(Producto $producto, float $metros, int $idPedido): MovimientoStock
    {
        $metros = round($metros, 2);
        $this->exigirCorte($producto);

        if ($metros <= 0) {
            throw new ErrorDeStock('Los metros vendidos tienen que ser más que cero.');
        }

        return DB::transaction(function () use ($producto, $metros, $idPedido) {
            $stock = $this->bloquear($producto);
            $rollo = $this->rolloParaCortar($producto, $stock, $metros);
            $this->moverMetros($rollo, -$metros);

            return $this->registrar($producto, 'VENTA', null, -$metros, 'METRO', $rollo, idPedido: $idPedido);
        });
    }

    /**
     * Devuelve al stock lo que salió por un pedido que se anula (RN-14):
     * las piezas vuelven a las cerradas y los metros, al rollo del que se
     * cortaron. Si el pedido ya se repuso, no hace nada.
     */
    public function reponerPedido(int $idPedido): void
    {
        DB::transaction(function () use ($idPedido) {
            $ventas = MovimientoStock::where('id_pedido', $idPedido)
                ->where('tipo', 'VENTA')
                ->orderBy('id_movimiento')
                ->get();

            if ($ventas->isEmpty()) {
                return;
            }

            // Se bloquean todos los productos del pedido de una vez y en el
            // mismo orden, para no trabarse con otra operación que también
            // bloquee varios.
            $stocks = Stock::whereIn('id_producto', $ventas->pluck('id_producto')->unique())
                ->orderBy('id_producto')
                ->lockForUpdate()
                ->get()
                ->keyBy('id_producto');

            $yaRepuesto = MovimientoStock::where('id_pedido', $idPedido)->where('tipo', 'ANULACION')->exists();

            if ($yaRepuesto) {
                return;
            }

            foreach ($ventas as $venta) {
                $cantidad = abs($venta->cantidad);

                if ($venta->id_rollo) {
                    $rollo = RolloAbierto::lockForUpdate()->findOrFail($venta->id_rollo);
                    $this->moverMetros($rollo, $cantidad);
                } else {
                    $this->sumarPiezas($stocks[$venta->id_producto], (int) $cantidad);
                }

                $this->registrar($venta->producto, 'ANULACION', null, $cantidad, $venta->unidad, $venta->rollo, $idPedido);
            }
        });
    }

    /**
     * El rollo del que sale un corte (RN-05, RN-09, RN-10): entre los
     * abiertos que alcanzan, el de menos metros restantes (SUP-03). Si
     * ninguno alcanza, se abre una pieza cerrada. Un corte sale siempre de
     * un único rollo: no se juntan metros de dos.
     */
    private function rolloParaCortar(Producto $producto, Stock $stock, float $metros): RolloAbierto
    {
        $rollo = RolloAbierto::where('id_producto', $producto->id_producto)
            ->abiertos()
            ->where('metros_restantes', '>=', $metros)
            ->orderBy('metros_restantes')
            ->orderBy('id_rollo')
            ->lockForUpdate()
            ->first();

        if ($rollo) {
            return $rollo;
        }

        if ($metros > $producto->metraje_rollo) {
            throw new ErrorDeStock('Un corte sale de un solo rollo, y el rollo trae '.$producto->metraje_rollo.' m. Para más metros, vendé rollos enteros.');
        }

        if ($stock->cantidad_cerrada < 1) {
            throw new ErrorDeStock('Ningún rollo abierto tiene '.Stock::formatearCantidad($metros).' m seguidos y no quedan rollos cerrados para abrir.');
        }

        $this->sumarPiezas($stock, -1);

        $rollo = new RolloAbierto;
        $rollo->id_producto = $producto->id_producto;
        $rollo->metros_iniciales = $producto->metraje_rollo;
        $rollo->metros_restantes = $producto->metraje_rollo;
        $rollo->save();

        return $rollo->refresh();
    }

    /**
     * Suma (o resta, si es negativo) metros a un rollo. En 0 pasa a AGOTADO
     * (RN-11); si vuelve a tener metros, a ABIERTO.
     */
    private function moverMetros(RolloAbierto $rollo, float $metros): void
    {
        $rollo->metros_restantes = round($rollo->metros_restantes + $metros, 2);
        $rollo->estado = $rollo->metros_restantes == 0 ? 'AGOTADO' : 'ABIERTO';
        $rollo->save();
    }

    /**
     * Bloquea la fila de STOCK del producto hasta el final de la transacción.
     * La primera vez que el producto mueve stock la fila todavía no existe:
     * se crea en 0.
     */
    private function bloquear(Producto $producto): Stock
    {
        DB::insert('INSERT INTO stock (id_producto) VALUES (?) ON CONFLICT (id_producto) DO NOTHING', [$producto->id_producto]);

        return Stock::lockForUpdate()->findOrFail($producto->id_producto);
    }

    private function sumarPiezas(Stock $stock, int $cantidad): void
    {
        $stock->newQuery()->whereKey($stock->id_producto)->update([
            'cantidad_cerrada' => DB::raw('cantidad_cerrada + '.$cantidad),
            'fecha_actualizacion' => DB::raw('LOCALTIMESTAMP(0)'),
        ]);

        $stock->cantidad_cerrada += $cantidad;
    }

    private function exigirPiezas(Producto $producto, Stock $stock, int $cantidad): void
    {
        if ($stock->cantidad_cerrada >= $cantidad) {
            return;
        }

        $mensaje = 'Hay '.Producto::cantidadConUnidad($stock->cantidad_cerrada, $producto->unidad_venta)
            .' y se necesitan '.Producto::cantidadConUnidad($cantidad, $producto->unidad_venta).'.';

        if ($producto->seCorta()) {
            $mensaje .= ' Los rollos ya cortados no cuentan como rollo entero.';
        }

        throw new ErrorDeStock($mensaje);
    }

    private function exigirCorte(Producto $producto): void
    {
        if (! $producto->seCorta()) {
            throw new ErrorDeStock('Este producto no se corta: se vende solo por pieza entera.');
        }
    }

    /**
     * Un ajuste lleva motivo (RN-30). Sumar stock con un ajuste solo vale
     * para una diferencia de inventario: lo demás entra como ingreso.
     */
    private function validarAjuste(float $cantidad, string $motivo): void
    {
        if (! array_key_exists($motivo, MovimientoStock::MOTIVOS_AJUSTE)) {
            throw new ErrorDeStock('Elegí el motivo del ajuste.');
        }

        if ($cantidad == 0) {
            throw new ErrorDeStock('La cantidad del ajuste no puede ser cero.');
        }

        if ($cantidad > 0 && $motivo !== 'INVENTARIO') {
            throw new ErrorDeStock('Solo una diferencia de inventario puede sumar stock. La mercadería nueva se carga como ingreso.');
        }
    }

    private function registrar(Producto $producto, string $tipo, ?string $motivo, float $cantidad, string $unidad, ?RolloAbierto $rollo = null, ?int $idPedido = null, ?string $observacion = null): MovimientoStock
    {
        return MovimientoStock::create([
            'id_producto' => $producto->id_producto,
            'id_rollo' => $rollo?->id_rollo,
            'id_pedido' => $idPedido,
            'tipo' => $tipo,
            'motivo' => $motivo,
            'cantidad' => $cantidad,
            'unidad' => $unidad,
            'observacion' => $observacion,
        ])->refresh();
    }
}
