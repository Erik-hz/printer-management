<?php

namespace App\Modules\Access\Models;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Matriz de permisos por rol. Es el único lugar donde se definen;
 * las políticas, rutas y vistas consultan estas habilidades (Gates).
 */
class Permisos
{
    public const ADMINISTRADOR = 'administrador';
    public const RESPONSABLE_INVENTARIO = 'responsable_inventario';
    public const TECNICO = 'tecnico';
    public const CONSULTA = 'consulta';

    /** Habilidad => roles que la tienen. */
    private const MATRIZ = [
        'ver-impresoras' => [self::ADMINISTRADOR, self::RESPONSABLE_INVENTARIO, self::TECNICO, self::CONSULTA],
        'gestionar-impresoras' => [self::ADMINISTRADOR],
        'gestionar-catalogos' => [self::ADMINISTRADOR],
        'gestionar-usuarios' => [self::ADMINISTRADOR],
    ];

    public static function registrar(): void
    {
        foreach (self::MATRIZ as $habilidad => $roles) {
            Gate::define($habilidad, fn (User $usuario) => self::tieneRol($usuario, $roles));
        }
    }

    /** @param  array<int, string>  $roles */
    private static function tieneRol(User $usuario, array $roles): bool
    {
        return $usuario->activo && in_array($usuario->rol?->nombre, $roles, true);
    }
}