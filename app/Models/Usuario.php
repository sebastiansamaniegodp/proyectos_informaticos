<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Usuario del sistema (tabla USUARIO del DER). Login básico, sin roles.
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
        'activo',
    ];

    protected $hidden = [
        'clave_hash',
    ];

    protected function casts(): array
    {
        return [
            'clave_hash' => 'hashed',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'clave_hash';
    }
}
