<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_invitado_es_redirigido_al_login(): void
    {
        $this->get('/inicio')->assertRedirect(route('login'));
    }

    public function test_un_usuario_activo_con_credenciales_correctas_ingresa(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->post('/ingresar', ['email' => $usuario->email, 'password' => 'password'])
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_una_clave_incorrecta_no_ingresa(): void
    {
        $usuario = User::factory()->create();

        $this->post('/ingresar', ['email' => $usuario->email, 'password' => 'incorrecta'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_un_usuario_inactivo_no_ingresa_aunque_la_clave_sea_correcta(): void
    {
        $usuario = User::factory()->conRol('administrador')->inactivo()->create();

        $this->post('/ingresar', ['email' => $usuario->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_tras_cinco_intentos_fallidos_se_bloquea_incluso_con_la_clave_correcta(): void
    {
        $usuario = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/ingresar', ['email' => $usuario->email, 'password' => 'incorrecta']);
        }

        $this->post('/ingresar', ['email' => $usuario->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_los_campos_son_obligatorios(): void
    {
        $this->post('/ingresar', [])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_cerrar_sesion(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post('/salir')->assertRedirect(route('login'));

        $this->assertGuest();
    }
}