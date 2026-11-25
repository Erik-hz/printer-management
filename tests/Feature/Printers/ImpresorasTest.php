<?php

namespace Tests\Feature\Printers;

use App\Models\User;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Monitoring\Services\LecturaService;
use App\Modules\Printers\Models\Area;
use App\Modules\Printers\Models\EstadoImpresora;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Models\Lugar;
use App\Modules\Printers\Models\Marca;
use App\Modules\Printers\Models\ModeloImpresora;
use App\Modules\Printers\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpresorasTest extends TestCase
{
    use RefreshDatabase;

    private function base(): array
    {
        $zona = Zona::create(['nombre' => 'Zona T']);
        $lugar = Lugar::create(['zona_id' => $zona->id, 'nombre' => 'Lugar T']);
        $area = Area::create(['lugar_id' => $lugar->id, 'nombre' => 'Area T']);
        $marca = Marca::create(['nombre' => 'HP']);
        $modelo = ModeloImpresora::create([
            'marca_id' => $marca->id,
            'nombre' => 'Modelo T',
            'tipo_impresion' => 'mono',
            'tecnologia' => 'laser',
        ]);
        $activa = EstadoImpresora::firstOrCreate(['nombre' => 'activa'], ['es_operativo' => true]);
        $retirada = EstadoImpresora::firstOrCreate(['nombre' => 'retirada'], ['es_operativo' => false]);

        return compact('zona', 'lugar', 'area', 'marca', 'modelo', 'activa', 'retirada');
    }

    private function admin(): User
    {
        return User::factory()->conRol('administrador')->create();
    }

    /** Datos de formulario de una impresora de red válida. */
    private function datosRed(array $base, array $extra = []): array
    {
        return array_merge([
            'nombre' => 'PRN-T1',
            'modelo_id' => $base['modelo']->id,
            'area_id' => $base['area']->id,
            'estado_id' => $base['activa']->id,
            'tipo_conexion' => 'red',
            'direccion_ip' => '192.0.2.10',
        ], $extra);
    }

    /** Impresora creada directamente en la base (USB por defecto, sin IP). */
    private function crear(array $base, array $extra = []): Impresora
    {
        return Impresora::create(array_merge([
            'nombre' => 'PRN-T1',
            'modelo_id' => $base['modelo']->id,
            'area_id' => $base['area']->id,
            'estado_id' => $base['activa']->id,
            'tipo_conexion' => 'usb',
        ], $extra));
    }

    public function test_un_invitado_no_ve_las_impresoras(): void
    {
        $this->get('/impresoras')->assertRedirect(route('login'));
    }

    public function test_un_rol_de_consulta_ve_el_listado_pero_no_puede_crear(): void
    {
        $base = $this->base();
        $this->crear($base);
        $lector = User::factory()->conRol('consulta')->create();

        $this->actingAs($lector)->get('/impresoras')->assertOk()->assertSee('PRN-T1');
        $this->actingAs($lector)->get('/impresoras/crear')->assertForbidden();
        $this->actingAs($lector)->post('/impresoras', $this->datosRed($base, ['nombre' => 'PRN-NUEVA']))->assertForbidden();

        $this->assertDatabaseCount('impresoras', 1);
    }

    public function test_listado_muestra_resumen_y_niveles_de_tinta_de_la_ultima_lectura(): void
    {
        $base = $this->base();
        $impresora = $this->crear($base, [
            'tipo_conexion' => 'red',
            'direccion_ip' => '10.0.0.25',
        ]);
        $tipo = TipoConsumible::firstOrCreate(['nombre' => 'Toner']);

        (new LecturaService)->registrar([
            'impresora_id' => $impresora->id,
            'fecha_lectura' => '2026-11-18 10:00:00',
            'origen' => 'snmp',
            'contador_paginas' => 80,
            'niveles' => [[
                'tipo_consumible_id' => $tipo->id,
                'color' => 'negro',
                'nivel' => 0,
            ]],
        ]);
        (new LecturaService)->registrar([
            'impresora_id' => $impresora->id,
            'fecha_lectura' => '2026-11-19 10:00:00',
            'origen' => 'snmp',
            'contador_paginas' => 100,
            'niveles' => [
                ['tipo_consumible_id' => $tipo->id, 'color' => 'negro', 'nivel' => 0.10],
                ['tipo_consumible_id' => $tipo->id, 'color' => 'cian', 'nivel' => 0.20],
                ['tipo_consumible_id' => $tipo->id, 'color' => 'magenta', 'nivel' => 0.75],
            ],
        ]);

        $consulta = User::factory()->conRol('consulta')->create();
        $this->actingAs($consulta)
            ->get('/impresoras')
            ->assertOk()
            ->assertSee('Estado de tinta')
            ->assertSee('Críticos')
            ->assertSee('Bajos')
            ->assertSee('Normales')
            ->assertSee('10,0%')
            ->assertSee('20,0%')
            ->assertSee('75,0%')
            ->assertDontSee('<strong>0,0%</strong>', false)
            ->assertSee('role="progressbar"', false);
    }

    public function test_los_roles_sin_permiso_no_pueden_modificar_impresoras(): void
    {
        $base = $this->base();
        $impresora = $this->crear($base);

        foreach (['responsable_inventario', 'tecnico', 'consulta'] as $rol) {
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->get("/impresoras/{$impresora->id}/editar")->assertForbidden();
            $this->actingAs($usuario)->put("/impresoras/{$impresora->id}", $this->datosRed($base))->assertForbidden();
            $this->actingAs($usuario)->post("/impresoras/{$impresora->id}/retirar")->assertForbidden();
            $this->actingAs($usuario)->delete("/impresoras/{$impresora->id}")->assertForbidden();
        }

        $fresca = $impresora->fresh();
        $this->assertNull($fresca->deleted_at);
        $this->assertSame($base['activa']->id, $fresca->estado_id);
    }

    public function test_el_administrador_crea_una_impresora_de_red(): void
    {
        $base = $this->base();

        $this->actingAs($this->admin())
            ->post('/impresoras', $this->datosRed($base))
            ->assertRedirect(route('impresoras.index'));

        $this->assertDatabaseHas('impresoras', [
            'nombre' => 'PRN-T1',
            'tipo_conexion' => 'red',
            'direccion_ip' => '192.0.2.10',
        ]);
    }

    public function test_el_administrador_crea_una_impresora_usb_sin_ip(): void
    {
        $base = $this->base();

        $this->actingAs($this->admin())
            ->post('/impresoras', $this->datosRed($base, [
                'tipo_conexion' => 'usb',
                'direccion_ip' => null,
                'equipo_conectado' => 'PC-01',
            ]))
            ->assertRedirect(route('impresoras.index'));

        $this->assertDatabaseHas('impresoras', [
            'nombre' => 'PRN-T1',
            'tipo_conexion' => 'usb',
            'direccion_ip' => null,
            'equipo_conectado' => 'PC-01',
        ]);
    }

    public function test_una_impresora_de_red_sin_ip_se_rechaza(): void
    {
        $base = $this->base();

        $this->actingAs($this->admin())
            ->post('/impresoras', $this->datosRed($base, ['direccion_ip' => null]))
            ->assertSessionHasErrors(['direccion_ip' => 'La dirección IP es obligatoria para las impresoras de red.']);

        $this->assertDatabaseCount('impresoras', 0);
    }

    public function test_una_impresora_usb_con_ip_se_rechaza(): void
    {
        $base = $this->base();

        $this->actingAs($this->admin())
            ->post('/impresoras', $this->datosRed($base, ['tipo_conexion' => 'usb', 'direccion_ip' => '192.0.2.20']))
            ->assertSessionHasErrors(['direccion_ip' => 'Las impresoras USB no tienen dirección IP.']);

        $this->assertDatabaseCount('impresoras', 0);
    }

    public function test_una_impresora_de_red_con_equipo_conectado_se_rechaza(): void
    {
        $base = $this->base();

        $this->actingAs($this->admin())
            ->post('/impresoras', $this->datosRed($base, ['equipo_conectado' => 'PC-01']))
            ->assertSessionHasErrors('equipo_conectado');

        $this->assertDatabaseCount('impresoras', 0);
    }

    public function test_una_ip_duplicada_se_rechaza(): void
    {
        $base = $this->base();
        $this->crear($base, ['nombre' => 'PRN-A', 'tipo_conexion' => 'red', 'direccion_ip' => '192.0.2.10']);

        $this->actingAs($this->admin())
            ->post('/impresoras', $this->datosRed($base, ['nombre' => 'PRN-B']))
            ->assertSessionHasErrors(['direccion_ip' => 'Esa dirección IP ya está asignada a otra impresora.']);

        $this->assertDatabaseCount('impresoras', 1);
    }

    public function test_una_ip_con_ceros_a_la_izquierda_se_guarda_normalizada(): void
    {
        $base = $this->base();

        $this->actingAs($this->admin())
            ->post('/impresoras', $this->datosRed($base, ['direccion_ip' => '172.20.015.25']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('impresoras', ['nombre' => 'PRN-T1', 'direccion_ip' => '172.20.15.25']);
    }

    public function test_los_valores_que_no_son_una_ip_se_rechazan(): void
    {
        $base = $this->base();
        $admin = $this->admin();

        foreach (['999.1.1.1', 'abc', '172.20.15.-', '192.168.1', 'USB'] as $i => $valor) {
            $this->actingAs($admin)
                ->post('/impresoras', $this->datosRed($base, ['nombre' => "PRN-X{$i}", 'direccion_ip' => $valor]))
                ->assertSessionHasErrors('direccion_ip');
        }

        $this->assertDatabaseCount('impresoras', 0);
    }

    public function test_un_nombre_duplicado_se_rechaza_sin_importar_las_mayusculas(): void
    {
        $base = $this->base();
        $this->crear($base, ['nombre' => 'PRN-T1']);

        $this->actingAs($this->admin())
            ->post('/impresoras', $this->datosRed($base, ['nombre' => 'prn-t1']))
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('impresoras', 1);
    }

    public function test_nombre_e_ip_se_pueden_reutilizar_tras_eliminar(): void
    {
        $base = $this->base();
        $impresora = $this->crear($base, ['tipo_conexion' => 'red', 'direccion_ip' => '192.0.2.10']);
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/impresoras/{$impresora->id}");

        $this->actingAs($admin)
            ->post('/impresoras', $this->datosRed($base))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Impresora::withTrashed()->count());
        $this->assertSame(1, Impresora::count());
    }

    public function test_actualizar_conserva_su_propia_ip_y_permite_cambiarla(): void
    {
        $base = $this->base();
        $impresora = $this->crear($base, ['tipo_conexion' => 'red', 'direccion_ip' => '192.0.2.10']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/impresoras/{$impresora->id}", $this->datosRed($base, ['nombre' => 'PRN-RENOMBRADA']))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->put("/impresoras/{$impresora->id}", $this->datosRed($base, [
                'nombre' => 'PRN-RENOMBRADA',
                'direccion_ip' => '192.0.2.99',
            ]))
            ->assertRedirect(route('impresoras.index'));

        $this->assertDatabaseHas('impresoras', [
            'id' => $impresora->id,
            'nombre' => 'PRN-RENOMBRADA',
            'direccion_ip' => '192.0.2.99',
        ]);
    }

    public function test_pasar_de_red_a_usb_exige_quitar_la_ip(): void
    {
        $base = $this->base();
        $impresora = $this->crear($base, ['tipo_conexion' => 'red', 'direccion_ip' => '192.0.2.10']);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put("/impresoras/{$impresora->id}", $this->datosRed($base, ['tipo_conexion' => 'usb']))
            ->assertSessionHasErrors('direccion_ip');

        $this->assertDatabaseHas('impresoras', ['id' => $impresora->id, 'tipo_conexion' => 'red']);

        $this->actingAs($admin)
            ->put("/impresoras/{$impresora->id}", $this->datosRed($base, [
                'tipo_conexion' => 'usb',
                'direccion_ip' => null,
                'equipo_conectado' => 'PC-9',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('impresoras', [
            'id' => $impresora->id,
            'tipo_conexion' => 'usb',
            'direccion_ip' => null,
            'equipo_conectado' => 'PC-9',
        ]);
    }

    public function test_retirar_cambia_el_estado_y_conserva_la_impresora(): void
    {
        $base = $this->base();
        $impresora = $this->crear($base);

        $this->actingAs($this->admin())
            ->post("/impresoras/{$impresora->id}/retirar")
            ->assertRedirect(route('impresoras.index'));

        $fresca = $impresora->fresh();
        $this->assertSame($base['retirada']->id, $fresca->estado_id);
        $this->assertNull($fresca->deleted_at);
    }

    public function test_eliminar_es_un_borrado_logico_y_desaparece_del_listado(): void
    {
        $base = $this->base();
        $impresora = $this->crear($base);
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/impresoras/{$impresora->id}")->assertRedirect(route('impresoras.index'));

        $this->assertSoftDeleted('impresoras', ['id' => $impresora->id]);
        $this->actingAs($admin)->get('/impresoras')->assertOk()->assertDontSee('PRN-T1');
    }

    public function test_las_claves_foraneas_inexistentes_se_rechazan(): void
    {
        $base = $this->base();

        $this->actingAs($this->admin())
            ->post('/impresoras', $this->datosRed($base, [
                'modelo_id' => 999999,
                'area_id' => 999999,
                'estado_id' => 999999,
            ]))
            ->assertSessionHasErrors(['modelo_id', 'area_id', 'estado_id']);

        $this->assertDatabaseCount('impresoras', 0);
    }

    public function test_el_listado_se_filtra_por_conexion_marca_y_busqueda(): void
    {
        $base = $this->base();
        $epson = Marca::create(['nombre' => 'Epson']);
        $modeloEpson = ModeloImpresora::create([
            'marca_id' => $epson->id,
            'nombre' => 'Modelo E',
            'tipo_impresion' => 'color',
            'tecnologia' => 'tinta',
        ]);

        $this->crear($base, ['nombre' => 'PRN-RED', 'tipo_conexion' => 'red', 'direccion_ip' => '192.0.2.10']);
        $this->crear($base, ['nombre' => 'PRN-USB', 'modelo_id' => $modeloEpson->id]);
        $lector = User::factory()->conRol('consulta')->create();

        $this->actingAs($lector)->get('/impresoras?conexion=usb')
            ->assertOk()->assertSee('PRN-USB')->assertDontSee('PRN-RED');

        $this->actingAs($lector)->get("/impresoras?marca={$base['marca']->id}")
            ->assertOk()->assertSee('PRN-RED')->assertDontSee('PRN-USB');

        $this->actingAs($lector)->get('/impresoras?q=192.0.2.10')
            ->assertOk()->assertSee('PRN-RED')->assertDontSee('PRN-USB');
    }

    public function test_los_formularios_de_alta_y_edicion_se_muestran(): void
    {
        $base = $this->base();
        $impresora = $this->crear($base);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/impresoras/crear')->assertOk()->assertSee('Nueva impresora');
        $this->actingAs($admin)->get("/impresoras/{$impresora->id}/editar")->assertOk()->assertSee('Editar impresora');
    }
}