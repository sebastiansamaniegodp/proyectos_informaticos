<?php

namespace App\Livewire\Pedidos;

use App\Exceptions\ErrorDePedido;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\PedidoDetalle;
use App\Models\Producto;
use App\Models\RolloAbierto;
use App\Services\PedidoService;
use App\Services\Redondeo;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Pedido nuevo o en borrador (pantallas "Pedido en borrador" y "Agregar
 * línea al pedido" del prototipo). Las líneas se arman en la pantalla y se
 * guardan todas juntas con PedidoService (RN-27): mientras no se toque
 * "Guardar" o "Confirmar" no se escribe nada en la base.
 *
 * Agregar o cambiar una línea abre un panel en la misma pantalla, así no se
 * pierde lo cargado.
 */
class Formulario extends Component
{
    public ?Pedido $pedido = null;

    public ?int $idCliente = null;

    public string $busquedaCliente = '';

    /**
     * Líneas del pedido. Cada una: id_producto, tipo_venta, cantidad,
     * precio_unitario, precio (LISTA, MAYORISTA o METRO) y descuento_pct.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $lineas = [];

    public string $flete = '0';

    public string $observacion = '';

    // Panel de la línea que se agrega o se cambia.

    public bool $panelLinea = false;

    /** Índice de la línea que se cambia; null si es una nueva. */
    public ?int $indiceLinea = null;

    public string $busquedaProducto = '';

    public ?int $idProducto = null;

    /** PIEZA (rollo entero, kilo, unidad) o METRO (corte). */
    public string $forma = 'PIEZA';

    /** LISTA o MAYORISTA, solo para la pieza entera (RN-18). */
    public string $precio = 'LISTA';

    public string $cantidad = '';

    public string $descuento = '';

    public bool $retazo = false;

    public function mount(?Pedido $pedido = null): void
    {
        if (! $pedido?->exists) {
            $this->pedido = null;
            $this->idCliente = request()->integer('cliente') ?: null;

            return;
        }

        if (! $pedido->esBorrador()) {
            $this->redirectRoute('pedidos.detalle', $pedido, navigate: true);

            return;
        }

        $this->pedido = $pedido;
        $this->idCliente = $pedido->id_cliente;
        $this->flete = Producto::formatearPrecio($pedido->monto_flete);
        $this->observacion = (string) $pedido->observacion;
        $this->lineas = $pedido->lineas()->with('producto')->get()
            ->map(fn (PedidoDetalle $linea) => [
                'id_producto' => $linea->id_producto,
                'tipo_venta' => $linea->tipo_venta,
                'cantidad' => $linea->cantidad,
                'precio_unitario' => $linea->precio_unitario,
                'precio' => match (true) {
                    $linea->esCorte() => 'METRO',
                    $linea->precio_unitario === $linea->producto->precio_mayorista
                        && $linea->precio_unitario !== $linea->producto->precio_venta => 'MAYORISTA',
                    default => 'LISTA',
                },
                'descuento_pct' => $linea->descuento_pct,
            ])
            ->all();
    }

    public function updated(string $propiedad): void
    {
        $this->resetErrorBag($propiedad);
    }

    public function updatedRetazo(): void
    {
        if ($this->retazo) {
            $this->descuento = '';
        }

        $this->resetErrorBag(['descuento', 'linea']);
    }

    public function updatedForma(): void
    {
        $this->retazo = false;
        $this->resetErrorBag();
    }

    // Cliente

    #[Computed]
    public function cliente(): ?Cliente
    {
        return $this->idCliente ? Cliente::find($this->idCliente) : null;
    }

    #[Computed]
    public function clientes(): Collection
    {
        return Cliente::activos()
            ->buscar($this->busquedaCliente)
            ->orderByRaw('upper(nombre)')
            ->limit(trim($this->busquedaCliente) === '' ? 5 : 8)
            ->get();
    }

    public function elegirCliente(int $idCliente): void
    {
        $this->idCliente = Cliente::activos()->whereKey($idCliente)->value('id_cliente');
        $this->busquedaCliente = '';
        $this->resetErrorBag('cliente');
    }

    public function cambiarCliente(): void
    {
        $this->idCliente = null;
    }

    // Panel de la línea

    public function abrirLinea(?int $indice = null): void
    {
        $this->resetErrorBag();
        $this->indiceLinea = null;
        $this->busquedaProducto = '';
        $this->idProducto = null;
        $this->forma = 'PIEZA';
        $this->precio = 'LISTA';
        $this->cantidad = '';
        $this->descuento = '';
        $this->retazo = false;

        if ($indice !== null && isset($this->lineas[$indice])) {
            $linea = $this->lineas[$indice];
            $this->indiceLinea = $indice;
            $this->idProducto = $linea['id_producto'];
            $this->forma = $linea['tipo_venta'] === 'METRO' ? 'METRO' : 'PIEZA';
            $this->precio = $linea['precio'] === 'MAYORISTA' ? 'MAYORISTA' : 'LISTA';
            $this->cantidad = self::escribirNumero($linea['cantidad']);
            $this->retazo = $linea['tipo_venta'] === 'METRO' && $linea['descuento_pct'] == RolloAbierto::DESCUENTO_RETAZO;
            $this->descuento = $this->retazo || ! $linea['descuento_pct'] ? '' : self::escribirNumero($linea['descuento_pct']);
        }

        $this->panelLinea = true;
    }

    public function cerrarLinea(): void
    {
        $this->panelLinea = false;
        $this->resetErrorBag();
    }

    #[Computed]
    public function productos(): Collection
    {
        $busqueda = trim($this->busquedaProducto);

        if ($busqueda === '') {
            return collect();
        }

        $patron = '%'.addcslashes($busqueda, '%_\\').'%';

        return Producto::activos()
            ->with('familia')
            ->where(fn ($query) => $query
                ->where('descripcion', 'ilike', $patron)
                ->orWhere('codigo', 'ilike', $patron))
            ->orderBy('id_familia')
            ->orderByDesc('altura_cm')
            ->orderBy('codigo')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function producto(): ?Producto
    {
        return $this->idProducto ? Producto::with('familia')->find($this->idProducto) : null;
    }

    public function elegirProducto(int $idProducto): void
    {
        $producto = Producto::activos()->find($idProducto);

        if (! $producto) {
            return;
        }

        $this->idProducto = $producto->id_producto;
        $this->busquedaProducto = '';
        $this->forma = 'PIEZA';
        $this->precio = 'LISTA';
        $this->retazo = false;
        $this->resetErrorBag();
    }

    public function cambiarProducto(): void
    {
        $this->idProducto = null;
        $this->resetErrorBag();
    }

    /**
     * La línea como la guarda PedidoService, con lo que hay en el panel.
     * Al cambiar una línea ya cargada se conserva su precio si no se cambió
     * el producto ni la forma de venta (RN-19).
     */
    private function lineaDelPanel(): ?array
    {
        $producto = $this->producto;

        if (! $producto) {
            return null;
        }

        $corte = $this->forma === 'METRO' && $producto->seCorta();
        $precio = $corte ? 'METRO' : ($this->precio === 'MAYORISTA' && $producto->precio_mayorista ? 'MAYORISTA' : 'LISTA');
        $anterior = $this->indiceLinea !== null ? ($this->lineas[$this->indiceLinea] ?? null) : null;

        $precioUnitario = $anterior && $anterior['id_producto'] === $producto->id_producto && $anterior['precio'] === $precio
            ? $anterior['precio_unitario']
            : match ($precio) {
                'METRO' => $producto->precio_metro,
                'MAYORISTA' => $producto->precio_mayorista,
                default => $producto->precio_venta,
            };

        return [
            'id_producto' => $producto->id_producto,
            'tipo_venta' => $corte ? 'METRO' : $producto->unidad_venta,
            'cantidad' => PedidoService::numero($this->cantidad),
            'precio_unitario' => $precioUnitario,
            'precio' => $precio,
            'descuento_pct' => $corte && $this->retazo
                ? RolloAbierto::DESCUENTO_RETAZO
                : (trim($this->descuento) === '' ? 0 : PedidoService::numero($this->descuento)),
        ];
    }

    /**
     * La cuenta de la línea en el orden de RN-24, para mostrarla mientras se
     * escribe: cantidad × precio, menos el descuento, redondeado.
     */
    #[Computed]
    public function calculo(): ?array
    {
        $linea = $this->lineaDelPanel();

        if (! $linea || ! $linea['cantidad'] || $linea['cantidad'] <= 0 || $linea['descuento_pct'] === null) {
            return null;
        }

        return [
            'linea' => $linea,
            'bruto' => (float) Redondeo::bruto($linea['cantidad'], $linea['precio_unitario']),
            'neto' => (float) Redondeo::neto($linea['cantidad'], $linea['precio_unitario'], $linea['descuento_pct']),
            'subtotal' => Redondeo::importeLinea($linea['cantidad'], $linea['precio_unitario'], $linea['descuento_pct']),
        ];
    }

    public function guardarLinea(PedidoService $pedidos): void
    {
        $linea = $this->lineaDelPanel();

        if (! $linea) {
            $this->addError('producto', 'Elegí el producto.');

            return;
        }

        if (! $linea['cantidad'] || $linea['cantidad'] <= 0) {
            $this->addError('cantidad', $linea['tipo_venta'] === 'METRO'
                ? 'Escribí cuántos metros, por ejemplo 7 o 7,5.'
                : 'Escribí cuántos '.$this->producto->nombrePiezas().', en números enteros.');

            return;
        }

        if ($linea['descuento_pct'] === null) {
            $this->addError('descuento', 'El descuento va en números, de 0 a '.PedidoService::DESCUENTO_MAXIMO.'.');

            return;
        }

        try {
            $previas = $this->pedido ? PedidoDetalle::where('id_pedido', $this->pedido->id_pedido)->get() : collect();
            $fila = $pedidos->armarLinea($linea, 1, $previas);
        } catch (ErrorDePedido $error) {
            $this->addError(str_contains($error->getMessage(), 'descuento') ? 'descuento' : 'cantidad', $error->getMessage());

            return;
        }

        $linea['cantidad'] = $fila['cantidad'];
        $linea['descuento_pct'] = $fila['descuento_pct'];

        if ($this->indiceLinea !== null && isset($this->lineas[$this->indiceLinea])) {
            $this->lineas[$this->indiceLinea] = $linea;
        } else {
            $this->lineas[] = $linea;
        }

        $this->panelLinea = false;
        $this->resetErrorBag('lineas');
    }

    public function quitarLinea(): void
    {
        if ($this->indiceLinea !== null) {
            unset($this->lineas[$this->indiceLinea]);
            $this->lineas = array_values($this->lineas);
        }

        $this->panelLinea = false;
    }

    // Totales y botones

    /**
     * Las líneas cargadas con su producto e importe, para la lista y los
     * totales de la pantalla.
     */
    #[Computed]
    public function filas(): Collection
    {
        $productos = Producto::with('familia')->whereIn('id_producto', array_column($this->lineas, 'id_producto'))->get()->keyBy('id_producto');

        return collect($this->lineas)->map(fn (array $linea, int $indice) => (object) [
            'indice' => $indice,
            'producto' => $productos[$linea['id_producto']],
            'linea' => $linea,
            'subtotal' => Redondeo::importeLinea($linea['cantidad'], $linea['precio_unitario'], $linea['descuento_pct']),
        ]);
    }

    /** Flete en guaraníes, o null si lo escrito no es un número. */
    private function montoFlete(): ?int
    {
        $flete = str_replace('.', '', trim($this->flete));

        if ($flete === '') {
            return 0;
        }

        return preg_match('/^\d{1,9}$/', $flete) ? (int) $flete : null;
    }

    #[Computed]
    public function totales(): array
    {
        $subtotal = $this->filas->sum('subtotal');
        $flete = $this->montoFlete() ?? 0;

        return ['subtotal' => $subtotal, 'flete' => $flete, 'total' => $subtotal + $flete];
    }

    public function guardar(PedidoService $pedidos): void
    {
        $pedido = $this->guardarBorrador($pedidos);

        if (! $pedido) {
            return;
        }

        session()->flash('aviso', 'Pedido guardado como borrador. No reserva mercadería hasta que lo confirmes.');

        $this->redirectRoute('pedidos.detalle', $pedido, navigate: true);
    }

    public function confirmar(PedidoService $pedidos): void
    {
        $pedido = $this->guardarBorrador($pedidos);

        if (! $pedido) {
            return;
        }

        try {
            $pedidos->confirmar($pedido);
        } catch (ErrorDePedido $error) {
            // El borrador quedó guardado: se sigue editando el mismo pedido.
            $this->pedido = $pedido;
            $this->js('history.replaceState(history.state, "", '.json_encode(route('pedidos.editar', $pedido)).')');
            $this->addError('confirmar', $error->getMessage());

            return;
        }

        session()->flash('aviso', 'Pedido confirmado. El stock ya se descontó.');

        $this->redirectRoute('pedidos.detalle', $pedido, navigate: true);
    }

    public function anular(PedidoService $pedidos): void
    {
        if (! $this->pedido) {
            $this->redirectRoute('pedidos.listado', navigate: true);

            return;
        }

        try {
            $pedidos->anular($this->pedido);
        } catch (ErrorDePedido $error) {
            $this->addError('confirmar', $error->getMessage());

            return;
        }

        session()->flash('aviso', 'Pedido N° '.$this->pedido->numero().' anulado.');

        $this->redirectRoute('pedidos.listado', navigate: true);
    }

    private function guardarBorrador(PedidoService $pedidos): ?Pedido
    {
        $this->resetErrorBag();

        if (! $this->cliente) {
            $this->addError('cliente', 'Elegí el cliente del pedido.');
        }

        if ($this->lineas === []) {
            $this->addError('lineas', 'Agregá al menos un producto al pedido.');
        }

        $flete = $this->montoFlete();

        if ($flete === null) {
            $this->addError('flete', 'El flete va en números, sin signo, por ejemplo 100.000. Si no hay flete, dejalo en 0.');
        }

        $observacion = preg_replace('/\s+/', ' ', trim($this->observacion)) ?: null;

        if ($observacion && mb_strlen($observacion) > 400) {
            $this->addError('observacion', 'La observación puede tener hasta 400 letras.');
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return null;
        }

        try {
            return $pedidos->guardar($this->pedido, $this->idCliente, $this->lineas, $flete, $observacion);
        } catch (ErrorDePedido $error) {
            $this->addError('confirmar', $error->getMessage());

            return null;
        }
    }

    /** 7.5 da "7,5"; 7.0 da "7". */
    private static function escribirNumero(float|int|string $numero): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format((float) $numero, 2, '.', ''), '0'), '.'));
    }

    public function render(): View
    {
        return view('livewire.pedidos.formulario')
            ->title($this->pedido ? 'Pedido N° '.$this->pedido->numero() : 'Nuevo pedido');
    }
}
