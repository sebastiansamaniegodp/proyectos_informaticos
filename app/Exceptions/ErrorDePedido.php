<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Operación sobre un pedido que las reglas de negocio no permiten: cambiar
 * un pedido que ya no está en borrador, pasar a un estado que no sigue,
 * confirmar sin stock, un descuento mayor al autorizado, etc. El mensaje
 * está escrito para mostrarlo tal cual en pantalla.
 */
class ErrorDePedido extends RuntimeException {}
