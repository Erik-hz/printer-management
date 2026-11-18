<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PermisosInventarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_matriz_de_permisos_de_inventario_por_rol(): void
    {
        $gestion = [
            'ver-inventario' => true,
            'gestionar-consumibles' => true,
            'registrar-movimientos' => true,
            'gestionar-compras' => true,
        ];
        $soloLectura = [
            'ver-inventario' => true,
            'gestionar-consumibles' => false,
            'registrar-movimientos' => false,
            'gestionar-compras' => false,
        ];

        $matriz = [
            'administrador' => $gestion,
            'responsable_inventario' => $gestion,
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

    public function test_un_usuario_inactivo_no_tiene_permisos_de_inventario(): void
    {
        $usuario = User::factory()->conRol('responsable_inventario')->inactivo()->create();

        foreach (['ver-inventario', 'gestionar-consumibles', 'registrar-movimientos', 'gestionar-compras'] as $habilidad) {
            $this->assertFalse(Gate::forUser($usuario)->allows($habilidad), $habilidad);
        }
    }
}