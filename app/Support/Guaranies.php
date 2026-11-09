<?php

namespace App\Support;

/**
 * Cómo se escriben los importes en guaraníes en los reportes y gráficas.
 */
class Guaranies
{
    /** 1300500 da "1.300.500". */
    public static function completo(int|float $importe): string
    {
        return number_format($importe, 0, ',', '.');
    }

    /**
     * Versión corta para ejes y etiquetas: 1.300.500 da "1,3 M", 850.000 da
     * "850 mil" y 12.000.000 da "12 M".
     */
    public static function compacto(int|float $importe): string
    {
        $signo = $importe < 0 ? '−' : '';
        $importe = abs($importe);

        if ($importe >= 1_000_000) {
            $millones = $importe / 1_000_000;
            $texto = number_format($millones, $millones < 10 ? 1 : 0, ',', '.');

            return $signo.preg_replace('/,0$/', '', $texto).' M';
        }

        if ($importe >= 1_000) {
            return $signo.number_format(round($importe / 1_000), 0, ',', '.').' mil';
        }

        return $signo.number_format($importe, 0, ',', '.');
    }

    /** 24.96 da "25%"; null da "—". */
    public static function porcentaje(?float $valor): string
    {
        return $valor === null ? '—' : number_format(round($valor), 0, ',', '.').'%';
    }

    /**
     * Máximo "redondo" del eje y el paso entre líneas guía, para que las
     * marcas queden en números limpios: 0, 2 M, 4 M, 6 M.
     *
     * @return array{0: int, 1: int}
     */
    public static function escala(int|float $maximo, int $marcas = 4): array
    {
        if ($maximo <= 0) {
            return [1_000_000, 250_000];
        }

        $bruto = $maximo / $marcas;
        $potencia = 10 ** floor(log10($bruto));
        $paso = collect([1, 2, 2.5, 5, 10])
            ->map(fn ($factor) => $factor * $potencia)
            ->first(fn ($paso) => $paso >= $bruto);

        return [(int) (ceil($maximo / $paso) * $paso), (int) $paso];
    }
}
