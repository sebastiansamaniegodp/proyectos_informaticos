<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El tablero de Inicio es solo para el administrador. A los demás roles los
 * manda a su pantalla de trabajo en vez de mostrarles un error, porque el
 * logo y las migas de todas las pantallas apuntan a Inicio.
 */
class InicioSegunRol
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->can('inicio')) {
            return redirect()->route($usuario->rol->rutaInicial());
        }

        return $next($request);
    }
}
