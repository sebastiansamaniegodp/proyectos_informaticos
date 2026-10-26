<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IngresoController extends Controller
{
    public function create(): View
    {
        return view('auth.ingreso');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'username' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Escribí tu usuario.',
            'password.required' => 'Escribí tu contraseña.',
        ]);

        $credenciales = [
            'username' => $datos['username'],
            'password' => $datos['password'],
            'activo' => 'S',
        ];

        if (! Auth::attempt($credenciales)) {
            throw ValidationException::withMessages([
                'username' => 'El usuario o la contraseña no son correctos.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
