<?php

namespace App\Services;

/**
 * Cálculo del importe de una línea del pedido (RN-20, RN-21, RN-24).
 *
 * El orden es siempre el mismo: cantidad × precio unitario, menos el
 * descuento, y recién al final se redondea al múltiplo de 500 Gs más
 * cercano. El precio unitario nunca se redondea antes de multiplicar
 * (caso PED-04). En un empate se redondea hacia arriba: 166.250 da 166.500
 * (SP-01).
 *
 * Las cuentas se hacen con bcmath, en decimal exacto: con float, 7 × 25.000
 * × 0,95 puede dar 166.249,99999 y redondear para el lado equivocado.
 */
class Redondeo
{
    public const MULTIPLO = 500;

    /**
     * Importe de la línea ya redondeado, en guaraníes.
     */
    public static function importeLinea(int|float|string $cantidad, int $precioUnitario, int|float|string $descuentoPct = 0): int
    {
        return self::a500(self::neto($cantidad, $precioUnitario, $descuentoPct));
    }

    /**
     * cantidad × precio, sin descuento ni redondeo. Puede tener decimales
     * cuando la cantidad los tiene (7,5 m).
     */
    public static function bruto(int|float|string $cantidad, int $precioUnitario): string
    {
        return bcmul(self::decimal($cantidad), (string) $precioUnitario, 4);
    }

    /**
     * cantidad × precio, menos el descuento, todavía sin redondear.
     */
    public static function neto(int|float|string $cantidad, int $precioUnitario, int|float|string $descuentoPct = 0): string
    {
        $porcentaje = bcsub('100', self::decimal($descuentoPct), 4);

        return bcdiv(bcmul(self::bruto($cantidad, $precioUnitario), $porcentaje, 4), '100', 4);
    }

    /**
     * Múltiplo de 500 más cercano; en el empate, el de arriba (SP-01).
     */
    public static function a500(int|float|string $importe): int
    {
        $importe = self::decimal($importe, 4);
        $mitad = (string) (self::MULTIPLO / 2);

        // bcdiv con escala 0 trunca: sumando la mitad antes, el truncado
        // queda en el múltiplo más cercano. Los importes nunca son negativos.
        $multiplos = bcdiv(bcadd($importe, $mitad, 4), (string) self::MULTIPLO, 0);

        return (int) bcmul($multiplos, (string) self::MULTIPLO, 0);
    }

    /**
     * Número en texto decimal con punto, como lo espera bcmath. Acepta la
     * coma decimal de la pantalla ("7,5").
     */
    private static function decimal(int|float|string $numero, int $decimales = 2): string
    {
        if (is_string($numero)) {
            $numero = (float) str_replace(',', '.', $numero);
        }

        return number_format((float) $numero, $decimales, '.', '');
    }
}
