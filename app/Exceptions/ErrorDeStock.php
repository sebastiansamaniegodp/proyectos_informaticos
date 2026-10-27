<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Operación de stock que las reglas de negocio no permiten: no alcanza el
 * stock, el producto no se corta, el ajuste no tiene motivo, etc. El
 * mensaje está escrito para mostrarlo tal cual en pantalla.
 */
class ErrorDeStock extends RuntimeException {}
