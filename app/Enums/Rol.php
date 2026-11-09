<?php

namespace App\Enums;

/**
 * Los tres roles del sistema y lo que puede hacer cada uno.
 *
 * Ver el stock y el catálogo es para todos. El resto son permisos:
 *
 *   inicio           tablero de la dueña (ventas, ganancia)   ADMIN
 *   pedidos          cargar, confirmar, entregar, cobrar       ADMIN, VENDEDOR
 *   clientes         alta y edición de clientes                ADMIN, VENDEDOR
 *   stock.mover      ingresos y ajustes de stock               ADMIN, DEPOSITO
 *   catalogo.editar  alta, precios y baja de productos         ADMIN
 *   reportes         resultado del mes                         ADMIN
 *   usuarios         alta de usuarios y cambio de rol          ADMIN
 *
 * Cada permiso es un Gate con el mismo nombre (AppServiceProvider).
 */
enum Rol: string
{
    case Admin = 'ADMIN';
    case Vendedor = 'VENDEDOR';
    case Deposito = 'DEPOSITO';

    public const PERMISOS = [
        'inicio' => [self::Admin],
        'pedidos' => [self::Admin, self::Vendedor],
        'clientes' => [self::Admin, self::Vendedor],
        'stock.mover' => [self::Admin, self::Deposito],
        'catalogo.editar' => [self::Admin],
        'reportes' => [self::Admin],
        'usuarios' => [self::Admin],
    ];

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Vendedor => 'Vendedor',
            self::Deposito => 'Depósito',
        };
    }

    public function puede(string $permiso): bool
    {
        return in_array($this, self::PERMISOS[$permiso] ?? [], true);
    }

    /** Pantalla a la que entra cada rol después del login. */
    public function rutaInicial(): string
    {
        return match ($this) {
            self::Admin => 'inicio',
            self::Vendedor => 'pedidos.listado',
            self::Deposito => 'stock.listado',
        };
    }
}
