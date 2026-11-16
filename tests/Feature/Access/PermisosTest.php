<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PermisosTest extends TestCase
{
    use RefreshDatabase;

    private const HABILIDADES = [
        'ver-impresoras',
        'gestionar-impresoras',
        'gestionar-catalogos',
        'gestionar-usuarios',
    ];

    public function test_la_matriz_de_permisos_por_rol(): void
    {
        $soloLectura = [
            'ver-impresoras' => true,
            'gestionar-impresoras' => false,
            'gestionar-catalogos' => false,
            'gestionar-usuarios' => false,
        ];

        $matriz = [
            'administrador' => [
                'ver-impresoras' => true,
                'gestionar-impresoras' => true,
                'gestionar-catalogos' => true,
                'gestionar-usuarios' => true,
            ],
            'responsable_inventario' => $soloLectura,
            'tecnico' => $soloLectura,
            'consulta' => $soloLectura,
        ];

        foreach ($matriz as $rol => $permisos) {
            $usuario = User::factory()->conRol($rol)->create();

            foreach ($permisos as $habilidad => $esperado) {
                $this->assertSame(
                    $esperado,
                    Gate::forUser($usuario)->allows($habilidad),
                    "Rol {$rol}, habilidad {$habilidad}"
                );
            }
        }
    }

    public function test_un_usuario_inactivo_no_tiene_ningun_permiso(): void
    {
        $usuario = User::factory()->conRol('administrador')->inactivo()->create();

        foreach (self::HABILIDADES as $habilidad) {
            $this->assertFalse(
                Gate::forUser($usuario)->allows($habilidad),
                "Un usuario inactivo no debe tener {$habilidad}"
            );
        }
    }
}