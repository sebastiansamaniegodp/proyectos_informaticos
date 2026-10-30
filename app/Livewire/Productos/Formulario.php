<?php

namespace App\Livewire\Productos;

use App\Models\Familia;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Pantalla "Alta de producto" del prototipo. La misma pantalla sirve para
 * editar un producto existente, que es a donde lleva cada fila del catálogo.
 */
class Formulario extends Component
{
    use WithFileUploads;

    private const PRECIOS = ['precio_venta', 'precio_metro', 'precio_mayorista', 'costo'];

    public ?Producto $producto = null;

    public ?int $id_familia = null;

    public string $altura = '';

    public string $abertura = '';

    public string $calibre = '';

    public string $unidad_venta = 'ROLLO';

    public bool $fraccionable = false;

    public string $metraje = '';

    // Los precios se escriben con puntos de miles, como en la lista.
    public string $precio_venta = '';

    public string $precio_metro = '';

    public string $precio_mayorista = '';

    public string $costo = '';

    /** @var TemporaryUploadedFile|null Ya llega achicada desde el navegador. */
    public $foto = null;

    public function mount(?Producto $producto = null): void
    {
        if (! $producto?->exists) {
            $this->producto = null;

            return;
        }

        $this->producto = $producto;
        $this->id_familia = $producto->id_familia;
        $this->altura = (string) $producto->altura_cm;
        $this->abertura = (string) $producto->abertura;
        $this->calibre = (string) $producto->calibre;
        $this->unidad_venta = $producto->unidad_venta;
        $this->fraccionable = $producto->seCorta();
        $this->metraje = (string) $producto->metraje_rollo;

        foreach (self::PRECIOS as $campo) {
            $this->{$campo} = Producto::formatearPrecio($producto->{$campo});
        }
    }

    public function updated(string $propiedad): void
    {
        // El aviso de un campo se va cuando se corrige; el del código depende
        // de varios campos, así que se va con cualquier cambio.
        $this->resetErrorBag([$propiedad, 'codigo']);

        if (in_array($propiedad, self::PRECIOS, true)) {
            $this->{$propiedad} = Producto::formatearPrecio(self::entero($this->{$propiedad}));
        }
    }

    public function updatedFoto(): void
    {
        $this->validate(['foto' => ['nullable', 'image', 'max:2048']], [
            'foto.image' => 'La foto tiene que ser una imagen.',
            'foto.max' => 'La foto es muy pesada.',
        ]);
    }

    #[Computed]
    public function familias(): Collection
    {
        return Familia::activas()->get();
    }

    /**
     * Código que se va armando mientras se completa el formulario.
     */
    #[Computed]
    public function codigo(): ?string
    {
        $familia = $this->familias->firstWhere('id_familia', $this->id_familia);

        if (! $familia) {
            return null;
        }

        return Producto::armarCodigo(
            $familia,
            $this->abertura,
            $this->calibre,
            self::entero($this->altura),
            $this->unidad_venta,
            $this->esRollo() ? self::entero($this->metraje) : null,
        );
    }

    public function esRollo(): bool
    {
        return $this->unidad_venta === 'ROLLO';
    }

    public function seCorta(): bool
    {
        return $this->esRollo() && $this->fraccionable;
    }

    public function guardar(): void
    {
        $familiaElegida = $this->familias->firstWhere('id_familia', $this->id_familia);

        $datos = Validator::make([
            'id_familia' => $this->id_familia,
            'altura' => self::numero($this->altura),
            'abertura' => trim($this->abertura) ?: null,
            'calibre' => trim($this->calibre) ?: null,
            'unidad_venta' => $this->unidad_venta,
            'metraje' => $this->esRollo() ? self::numero($this->metraje) : null,
            'precio_venta' => self::numero($this->precio_venta),
            'precio_metro' => $this->seCorta() ? self::numero($this->precio_metro) : null,
            'precio_mayorista' => self::numero($this->precio_mayorista),
            'costo' => self::numero($this->costo),
            'foto' => $this->foto,
        ], [
            'id_familia' => ['required', Rule::exists('familia', 'id_familia')->where('activo', 'S')],
            'altura' => ['nullable', 'integer', 'between:10,1000'],
            'abertura' => ['nullable', 'string', 'max:20', function ($atributo, $valor, $fallar) use ($familiaElegida) {
                // DD-16: el hexagonal en pulgadas; la malla del resto, en centímetros.
                if ($familiaElegida?->prefijoCodigo() === 'HEX') {
                    if (! preg_match('/^(\d+|\d+\s*\/\s*\d+|\d+\s+\d+\s*\/\s*\d+|\d*\s*[½¼¾])\s*("|”|\'\'|pulg\.?)$/u', $valor)) {
                        $fallar('En el hexagonal la abertura va en pulgadas, por ejemplo 1/2" o 2".');
                    }
                } elseif (! preg_match('/^\d+([.,]\d+)?(\s*x\s*\d+([.,]\d+)?)?\s*cm$/iu', $valor)) {
                    $fallar('La abertura va en centímetros, por ejemplo 5 cm o 5 x 5 cm.');
                }
            }],
            'calibre' => ['nullable', 'string', 'max:10', 'regex:/\d/'],
            'unidad_venta' => ['required', Rule::in(array_keys(Producto::UNIDADES))],
            // RN-02: si se corta por metro, el rollo necesita su metraje y el precio del metro.
            'metraje' => [Rule::requiredIf($this->seCorta()), 'nullable', 'integer', 'between:1,1000'],
            'precio_venta' => ['required', 'integer', 'min:1', 'max:999999999'],
            // El metro cortado cuesta menos que el rollo entero, y el mayorista no supera al de lista.
            'precio_metro' => [Rule::requiredIf($this->seCorta()), 'nullable', 'integer', 'min:1', 'lt:precio_venta'],
            'precio_mayorista' => ['nullable', 'integer', 'min:1', 'lte:precio_venta'],
            // Sin costo el resultado del mes no puede calcular la utilidad (RN-33).
            'costo' => ['required', 'integer', 'min:0', 'max:999999999'],
            'foto' => ['nullable', 'image', 'max:2048'],
        ], [
            'id_familia.required' => 'Elegí la familia.',
            'id_familia.exists' => 'Elegí la familia.',
            'altura.integer' => 'La altura va solo con números, en centímetros. Por ejemplo 150.',
            'altura.between' => 'La altura va en centímetros, entre 10 y 1000. Por ejemplo 150 para 1,50 m.',
            'abertura.max' => 'La abertura puede tener hasta 20 letras.',
            'calibre.max' => 'El calibre puede tener hasta 10 letras.',
            'calibre.regex' => 'El calibre lleva el número, por ejemplo n° 16.',
            'unidad_venta.*' => 'Elegí la unidad de venta.',
            'metraje.required' => 'Escribí cuántos metros trae el rollo.',
            'metraje.integer' => 'Los metros del rollo van solo con números, por ejemplo 50.',
            'metraje.between' => 'Los metros del rollo van entre 1 y 1000.',
            'precio_venta.required' => 'Escribí el precio.',
            'precio_venta.*' => 'El precio va solo con números, por ejemplo 1.134.000.',
            'precio_metro.required' => 'Escribí el precio por metro.',
            'precio_metro.lt' => 'El precio por metro tiene que ser menor que el del rollo entero.',
            'precio_metro.*' => 'El precio por metro va solo con números, por ejemplo 25.000.',
            'precio_mayorista.lte' => 'El precio mayorista no puede ser mayor que el de lista.',
            'precio_mayorista.*' => 'El precio mayorista va solo con números.',
            'costo.required' => 'Escribí el costo. Sin costo no se puede calcular la utilidad.',
            'costo.*' => 'El costo va solo con números.',
            'foto.*' => 'La foto tiene que ser una imagen liviana.',
        ])->validate();

        $familia = $this->familias->firstWhere('id_familia', $datos['id_familia']);
        $codigo = Producto::armarCodigo(
            $familia,
            $datos['abertura'],
            $datos['calibre'],
            $datos['altura'],
            $datos['unidad_venta'],
            $datos['metraje'],
        );

        $repetido = Producto::where('codigo', $codigo)
            ->when($this->producto, fn ($query) => $query->whereKeyNot($this->producto->id_producto))
            ->exists();

        if ($repetido) {
            $this->addError('codigo', "Ya hay un producto con el código {$codigo}. Revisá la altura, la abertura, el calibre o la presentación.");

            return;
        }

        $medidas = Producto::armarMedidas($datos['abertura'], $datos['calibre'], $datos['altura'], $datos['metraje']);

        $producto = $this->producto ?? new Producto(['activo' => 'S']);
        $fotoAnterior = $producto->foto;

        $producto->fill([
            'codigo' => $codigo,
            'descripcion' => Str::limit(trim($familia->nombre.' '.$medidas), 150, ''),
            'id_familia' => $familia->id_familia,
            'unidad_venta' => $datos['unidad_venta'],
            'fraccionable' => $this->seCorta() ? 'S' : 'N',
            'metraje_rollo' => $datos['metraje'],
            'altura_cm' => $datos['altura'],
            'abertura' => $datos['abertura'],
            'calibre' => $datos['calibre'],
            'precio_venta' => $datos['precio_venta'],
            'precio_metro' => $datos['precio_metro'],
            'precio_mayorista' => $datos['precio_mayorista'],
            'costo' => $datos['costo'],
        ]);

        if ($this->foto) {
            $producto->foto = $this->foto->store('productos', 'local');
        }

        try {
            $producto->save();
        } catch (\Throwable $error) {
            if ($this->foto) {
                Storage::disk('local')->delete($producto->foto);
            }

            throw $error;
        }

        if ($this->foto && $fotoAnterior) {
            Storage::disk('local')->delete($fotoAnterior);
        }

        $this->redirectRoute('productos.catalogo', navigate: true);
    }

    /**
     * Baja lógica: el producto deja de aparecer en el catálogo y en el stock,
     * pero no se borra nunca, porque puede estar en pedidos ya hechos. Con
     * la misma acción se vuelve a activar.
     */
    public function cambiarEstado(): void
    {
        if (! $this->producto) {
            return;
        }

        $activo = $this->producto->activo === 'S';
        $this->producto->update(['activo' => $activo ? 'N' : 'S']);

        session()->flash('aviso', $activo
            ? "Se dio de baja {$this->producto->codigo}. Se puede volver a activar desde los dados de baja."
            : "Se volvió a activar {$this->producto->codigo}.");

        $this->redirectRoute('productos.catalogo', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.productos.formulario')
            ->title($this->producto ? 'Editar producto' : 'Nuevo producto');
    }

    /**
     * Pasa "1.134.000" a 1134000. Vacío queda en null.
     */
    private static function entero(string $texto): ?int
    {
        $digitos = preg_replace('/\D/', '', $texto);

        return $digitos === '' ? null : (int) $digitos;
    }

    /**
     * Como entero(), pero para validar: si el texto trae algo que no son
     * dígitos ni puntos de miles, lo devuelve tal cual para que la regla
     * integer lo rechace en vez de quitarle las letras en silencio.
     */
    private static function numero(string $texto): int|string|null
    {
        $texto = trim($texto);

        if ($texto !== '' && ! preg_match('/^[\d.\s]+$/', $texto)) {
            return $texto;
        }

        return self::entero($texto);
    }
}
