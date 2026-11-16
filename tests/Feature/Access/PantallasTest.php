<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PantallasTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pantalla_de_login_muestra_el_formulario(): void
    {
        $this->get('/ingresar')
            ->assertOk()
            ->assertSee('Iniciar sesión')
            ->assertSee('Correo electrónico')
            ->assertSee('Contraseña');
    }

    public function test_el_inicio_muestra_el_nombre_y_el_rol_del_usuario(): void
    {
        $usuario = User::factory()->conRol('responsable_inventario')->create(['name' => 'Usuario Prueba']);

        $this->actingAs($usuario)
            ->get('/inicio')
            ->assertOk()
            ->assertSee('Usuario Prueba')
            ->assertSee('Responsable inventario');
    }

    public function test_un_usuario_autenticado_no_ve_el_login(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/ingresar')->assertRedirect();
    }

    public function test_la_raiz_redirige_al_inicio(): void
    {
        $this->get('/')->assertRedirect('/inicio');
    }
}