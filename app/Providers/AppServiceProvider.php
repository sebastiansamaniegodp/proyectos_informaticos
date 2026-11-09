<?php

namespace App\Providers;

use App\Enums\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Un Gate por permiso de Rol::PERMISOS. Las rutas los usan con el
        // middleware can: y las vistas con @can.
        foreach (array_keys(Rol::PERMISOS) as $permiso) {
            Gate::define($permiso, fn (Usuario $usuario) => $usuario->rol->puede($permiso));
        }
    }
}
