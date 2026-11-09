<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de un usuario que fue desactivado mientras estaba
 * adentro. El login ya rechaza a los desactivados, pero la sesión dura 30
 * días: sin esto seguirían entrando hasta que venza.
 *
 * Va en el grupo web, así también cubre los pedidos de Livewire.
 */
class SoloUsuariosActivos
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->activo !== 'S') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
