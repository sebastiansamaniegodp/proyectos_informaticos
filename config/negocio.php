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

    // Número de WhatsApp al que se abre el chat desde el botón "Enviar
    // comprobante" del pedido. Se escribe como se marca en Paraguay
    // (0971 578 168) o con el código de país (595971578168).
    'whatsapp' => env('NEGOCIO_WHATSAPP', '0971578168'),

];
