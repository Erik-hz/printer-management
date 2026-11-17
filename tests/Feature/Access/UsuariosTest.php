<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Modules\Access\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->conRol('administrador')->create();
    }

    private function rolId(string $nombre): int
    {
        return Rol::firstOrCreate(['nombre' => $nombre])->id;
    }

    /** Datos de formulario de un usuario nuevo válido. */
    private function datos(array $extra = []): array
    {
        return array_merge([
            'name' => 'Usuario Nuevo',
            'email' => 'nuevo@ejemplo.test',
            'rol_id' => $this->rolId('tecnico'),
            'activo' => '1',
            'password' => 'Clave-Segura-1',
            'password_confirmation' => 'Clave-Segura-1',
        ], $extra);
    }

    /** Datos de formulario para editar un usuario existente (sin contraseña). */
    private function datosEdicion(User $usuario, array $extra = []): array
    {
        return array_merge([
            'name' => $usuario->name,
            'email' => $usuario->email,
            'rol_id' => $usuario->rol_id,
            'activo' => '1',
        ], $extra);
    }

    public function test_un_invitado_no_ve_los_usuarios(): void
    {
        $this->get('/usuarios')->assertRedirect(route('login'));
    }

    public function test_los_roles_sin_permiso_no_gestionan_usuarios(): void
    {
        $objetivo = $this->admin();

        foreach (['responsable_inventario', 'tecnico', 'consulta'] as $rol) {
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->get('/usuarios')->assertForbidden();
            $this->actingAs($usuario)->get('/usuarios/crear')->assertForbidden();
            $this->actingAs($usuario)->post('/usuarios', $this->datos())->assertForbidden();
            $this->actingAs($usuario)->put("/usuarios/{$objetivo->id}", $this->datosEdicion($objetivo))->assertForbidden();
            $this->actingAs($usuario)->put("/usuarios/{$objetivo->id}/clave", [
                'password' => 'Otra-Clave-123',
                'password_confirmation' => 'Otra-Clave-123',
            ])->assertForbidden();
        }

        $this->assertDatabaseCount('users', 4);
        $this->assertTrue(Hash::check('password', $objetivo->fresh()->password));
    }

    public function test_el_administrador_ve_el_listado(): void
    {
        $admin = $this->admin();
        User::factory()->conRol('tecnico')->create(['name' => 'Tecnico Visible']);

        $this->actingAs($admin)->get('/usuarios')->assertOk()->assertSee('Tecnico Visible');
    }

    public function test_el_administrador_crea_un_usuario_con_la_contrasena_cifrada(): void
    {
        $this->actingAs($this->admin())
            ->post('/usuarios', $this->datos())
            ->assertRedirect(route('usuarios.index'));

        $usuario = User::where('email', 'nuevo@ejemplo.test')->firstOrFail();

        $this->assertSame($this->rolId('tecnico'), (int) $usuario->rol_id);
        $this->assertTrue((bool) $usuario->activo);
        $this->assertTrue(Hash::check('Clave-Segura-1', $usuario->password));
        $this->assertNotSame('Clave-Segura-1', $usuario->password);
    }

    public function test_el_correo_se_guarda_en_minusculas(): void
    {
        $this->actingAs($this->admin())
            ->post('/usuarios', $this->datos(['email' => '  NUEVO@Ejemplo.TEST ']))
            ->assertSessionHasNoErrors();

        $this->assertSame('nuevo@ejemplo.test', User::where('email', 'nuevo@ejemplo.test')->firstOrFail()->email);
    }

    public function test_un_correo_duplicado_se_rechaza_sin_importar_las_mayusculas(): void
    {
        User::factory()->create(['email' => 'existente@ejemplo.test']);

        $this->actingAs($this->admin())
            ->post('/usuarios', $this->datos(['email' => 'Existente@Ejemplo.test']))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 2);
    }

    public function test_las_contrasenas_debiles_se_rechazan(): void
    {
        $admin = $this->admin();

        $casos = [
            'corta' => 'Ab1',
            'sin numero' => 'SoloLetrasLargas',
            'sin letra' => '1234567890123',
        ];

        foreach ($casos as $clave) {
            $this->actingAs($admin)
                ->post('/usuarios', $this->datos(['password' => $clave, 'password_confirmation' => $clave]))
                ->assertSessionHasErrors('password');
        }

        $this->actingAs($admin)
            ->post('/usuarios', $this->datos(['password_confirmation' => 'Distinta-Clave-1']))
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_un_rol_inexistente_se_rechaza(): void
    {
        $this->actingAs($this->admin())
            ->post('/usuarios', $this->datos(['rol_id' => 999999]))
            ->assertSessionHasErrors('rol_id');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_editar_un_usuario_no_toca_su_contrasena(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->actingAs($this->admin())
            ->put("/usuarios/{$usuario->id}", $this->datosEdicion($usuario, [
                'name' => 'Nombre Cambiado',
                'rol_id' => $this->rolId('consulta'),
                'activo' => '0',
            ]))
            ->assertRedirect(route('usuarios.index'));

        $fresco = $usuario->fresh();
        $this->assertSame('Nombre Cambiado', $fresco->name);
        $this->assertSame($this->rolId('consulta'), (int) $fresco->rol_id);
        $this->assertFalse((bool) $fresco->activo);
        $this->assertTrue(Hash::check('password', $fresco->password));
    }

    public function test_editar_conserva_su_propio_correo(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->actingAs($this->admin())
            ->put("/usuarios/{$usuario->id}", $this->datosEdicion($usuario))
            ->assertSessionHasNoErrors();
    }

    public function test_el_ultimo_administrador_no_se_puede_desactivar(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/usuarios/{$admin->id}", $this->datosEdicion($admin, ['activo' => '0']))
            ->assertSessionHasErrors('general');

        $this->assertTrue((bool) $admin->fresh()->activo);
    }

    public function test_el_ultimo_administrador_no_puede_cambiar_de_rol(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/usuarios/{$admin->id}", $this->datosEdicion($admin, ['rol_id' => $this->rolId('tecnico')]))
            ->assertSessionHasErrors('general');

        $this->assertSame($admin->rol_id, $admin->fresh()->rol_id);
    }

    public function test_con_otro_administrador_activo_si_se_puede_desactivar_a_uno(): void
    {
        $admin = $this->admin();
        $otro = $this->admin();

        $this->actingAs($admin)
            ->put("/usuarios/{$otro->id}", $this->datosEdicion($otro, ['activo' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) $otro->fresh()->activo);
    }

    public function test_un_administrador_inactivo_no_cuenta_como_respaldo(): void
    {
        $admin = $this->admin();
        User::factory()->conRol('administrador')->inactivo()->create();

        $this->actingAs($admin)
            ->put("/usuarios/{$admin->id}", $this->datosEdicion($admin, ['activo' => '0']))
            ->assertSessionHasErrors('general');

        $this->assertTrue((bool) $admin->fresh()->activo);
    }

    public function test_el_administrador_restablece_la_contrasena_de_otro_usuario(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->actingAs($this->admin())
            ->put("/usuarios/{$usuario->id}/clave", [
                'password' => 'Nueva-Clave-9',
                'password_confirmation' => 'Nueva-Clave-9',
            ])
            ->assertRedirect(route('usuarios.edit', $usuario));

        $fresco = $usuario->fresh();
        $this->assertTrue(Hash::check('Nueva-Clave-9', $fresco->password));
        $this->assertFalse(Hash::check('password', $fresco->password));
    }

    public function test_restablecer_con_una_contrasena_debil_se_rechaza(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->actingAs($this->admin())
            ->put("/usuarios/{$usuario->id}/clave", ['password' => 'corta', 'password_confirmation' => 'corta'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $usuario->fresh()->password));
    }

    public function test_cambiar_la_propia_contrasena_con_la_actual_incorrecta_se_rechaza(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->actingAs($usuario)
            ->put('/perfil/clave', [
                'current_password' => 'incorrecta',
                'password' => 'Nueva-Clave-9',
                'password_confirmation' => 'Nueva-Clave-9',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $usuario->fresh()->password));
    }

    public function test_cambiar_la_propia_contrasena(): void
    {
        $usuario = User::factory()->conRol('consulta')->create();

        $this->actingAs($usuario)
            ->put('/perfil/clave', [
                'current_password' => 'password',
                'password' => 'Nueva-Clave-9',
                'password_confirmation' => 'Nueva-Clave-9',
            ])
            ->assertRedirect(route('perfil.clave'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Nueva-Clave-9', $usuario->fresh()->password));
    }

    public function test_la_nueva_contrasena_no_puede_ser_igual_a_la_actual_ni_debil(): void
    {
        $usuario = User::factory()->conRol('consulta')->create();

        $this->actingAs($usuario)
            ->put('/perfil/clave', [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors('password');

        $this->actingAs($usuario)
            ->put('/perfil/clave', [
                'current_password' => 'password',
                'password' => 'corta1',
                'password_confirmation' => 'corta1',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $usuario->fresh()->password));
    }

    public function test_cualquier_rol_activo_accede_al_cambio_de_contrasena(): void
    {
        foreach (['administrador', 'responsable_inventario', 'tecnico', 'consulta'] as $rol) {
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->get('/perfil/clave')->assertOk()->assertSee('Cambiar contraseña');
        }

        Auth::logout();
        $this->get('/perfil/clave')->assertRedirect(route('login'));
    }

    public function test_un_usuario_desactivado_pierde_el_acceso_con_la_sesion_abierta(): void
    {
        $usuario = User::factory()->conRol('tecnico')->create();

        $this->actingAs($usuario)->get('/inicio')->assertOk();

        $usuario->forceFill(['activo' => false])->save();

        $this->get('/inicio')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_los_formularios_de_usuarios_se_muestran(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/usuarios/crear')->assertOk()->assertSee('Nuevo usuario');
        $this->actingAs($admin)->get("/usuarios/{$admin->id}/editar")
            ->assertOk()
            ->assertSee('Editar usuario')
            ->assertSee('Restablecer contraseña');
    }
}