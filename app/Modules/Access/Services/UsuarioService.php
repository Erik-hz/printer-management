<?php

namespace App\Modules\Access\Services;

use App\Models\User;
use App\Modules\Access\Models\Rol;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UsuarioService
{
    public function crear(array $datos): User
    {
        $usuario = new User();
        $usuario->forceFill([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'],
            'rol_id' => $datos['rol_id'],
            'activo' => $datos['activo'],
        ])->save();

        return $usuario;
    }

    public function actualizar(User $usuario, array $datos): User
    {
        $this->protegerUltimoAdministrador($usuario, (int) $datos['rol_id'], (bool) $datos['activo']);

        $usuario->forceFill([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'rol_id' => $datos['rol_id'],
            'activo' => $datos['activo'],
        ])->save();

        return $usuario;
    }

    /** Sirve tanto para el restablecimiento por un administrador como para el cambio propio. */
    public function cambiarClave(User $usuario, string $clave): void
    {
        $usuario->forceFill(['password' => $clave])->setRememberToken(Str::random(60));
        $usuario->save();
    }

    /**
     * El sistema no puede quedarse sin un administrador activo. La comprobación no usa
     * bloqueos: dos administradores que se desactivaran a la vez en el mismo instante
     * podrían eludirla, un riesgo aceptado con tan pocos administradores.
     */
    private function protegerUltimoAdministrador(User $usuario, int $nuevoRolId, bool $nuevoActivo): void
    {
        $rolAdmin = (int) Rol::where('nombre', 'administrador')->value('id');

        $eraAdministradorActivo = $usuario->activo && (int) $usuario->rol_id === $rolAdmin;
        $seguiraSiendolo = $nuevoActivo && $nuevoRolId === $rolAdmin;

        if (! $eraAdministradorActivo || $seguiraSiendolo) {
            return;
        }

        $otros = User::where('activo', true)
            ->where('rol_id', $rolAdmin)
            ->where('id', '<>', $usuario->id)
            ->count();

        if ($otros === 0) {
            throw ValidationException::withMessages([
                'general' => 'Debe quedar al menos un administrador activo. No se puede desactivar ni cambiar el rol del último.',
            ]);
        }
    }
}