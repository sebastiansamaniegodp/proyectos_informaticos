<?php

/*
 * Datos del negocio que van en el comprobante del pedido (DD-08). El
 * comprobante es interno y no tiene validez fiscal: no lleva RUC ni
 * timbrado (SUP-07).
 */
return [

    'nombre' => env('NEGOCIO_NOMBRE', 'Shopping de los Tejidos'),

    // Línea de contacto debajo del nombre, por ejemplo "0981 123 456 · Salto
    // del Guairá". Si queda vacía no se muestra.
    'contacto' => env('NEGOCIO_CONTACTO'),

];
