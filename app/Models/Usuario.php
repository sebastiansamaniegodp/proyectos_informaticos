<?php

namespace App\Models;

use App\Enums\Rol;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Usuario del sistema (tabla USUARIO del DER). Login básico; lo que puede
 * hacer cada uno depende de su rol (App\Enums\Rol).
 */
class Usuario extends Authenticatable
{
    protected $table = 'usuario';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    /**
     * La tabla no tiene remember_token: la sesión de 30 días se logra con
     * SESSION_LIFETIME, no con la cookie "recordarme".
     */
    protected $rememberTokenName = '';

    protected $fillable = [
        'username',
        'clave_hash',
        'nombre',
        'rol',
        'activo',
    ];

    protected $hidden = [
        'clave_hash',
    ];

    /** Mismo valor por defecto que la columna, para un usuario recién creado. */
    protected $attributes = [
        'rol' => 'VENDEDOR',
    ];

    protected function casts(): array
    {
        return [
            'clave_hash' => 'hashed',
            'rol' => Rol::class,
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'clave_hash';
    }
}
