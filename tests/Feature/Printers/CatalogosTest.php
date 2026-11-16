<?php

namespace Tests\Feature\Printers;

use App\Models\User;
use App\Modules\Printers\Models\Area;
use App\Modules\Printers\Models\EstadoImpresora;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Models\Lugar;
use App\Modules\Printers\Models\Marca;
use App\Modules\Printers\Models\ModeloImpresora;
use App\Modules\Printers\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->conRol('administrador')->create();
    }

    private function modelo(string $marca = 'HP', string $nombre = 'Modelo Alfa'): ModeloImpresora
    {
        $marca = Marca::firstOrCreate(['nombre' => $marca]);

        return ModeloImpresora::create([
            'marca_id' => $marca->id,
            'nombre' => $nombre,
            'tipo_impresion' => 'mono',
            'tecnologia' => 'laser',
        ]);
    }

    private function impresoraEn(Area $area, ModeloImpresora $modelo): Impresora
    {
        $estado = EstadoImpresora::firstOrCreate(['nombre' => 'activa'], ['es_operativo' => true]);

        return Impresora::create([
            'nombre' => 'PRN-TEST-'.uniqid(),
            'modelo_id' => $modelo->id,
            'area_id' => $area->id,
            'estado_id' => $estado->id,
            'tipo_conexion' => 'usb',
        ]);
    }

    public function test_un_invitado_no_ve_los_catalogos(): void
    {
        $this->get('/zonas')->assertRedirect(route('login'));
        $this->get('/modelos')->assertRedirect(route('login'));
    }

    public function test_un_rol_de_consulta_ve_las_zonas_pero_no_puede_crearlas(): void
    {
        Zona::create(['nombre' => 'Zona A']);
        $lector = User::factory()->conRol('consulta')->create();

        $this->actingAs($lector)->get('/zonas')->assertOk()->assertSee('Zona A');
        $this->actingAs($lector)->post('/zonas', ['nombre' => 'Zona B', 'activa' => '1'])->assertForbidden();

        $this->assertDatabaseMissing('zonas', ['nombre' => 'Zona B']);
    }

    public function test_los_roles_sin_permiso_no_pueden_gestionar_catalogos(): void
    {
        foreach (['responsable_inventario', 'tecnico', 'consulta'] as $rol) {
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->post('/zonas', ['nombre' => 'X'])->assertForbidden();
            $this->actingAs($usuario)->post('/marcas', ['nombre' => 'X'])->assertForbidden();
            $this->actingAs($usuario)->get('/modelos/crear')->assertForbidden();
            $this->actingAs($usuario)->post('/modelos', [])->assertForbidden();
        }
    }

    public function test_el_administrador_crea_una_zona(): void
    {
        $this->actingAs($this->admin())
            ->post('/zonas', ['nombre' => 'Zona Nueva', 'activa' => '1'])
            ->assertRedirect(route('zonas.index'));

        $this->assertDatabaseHas('zonas', ['nombre' => 'Zona Nueva', 'activa' => true]);
    }

    public function test_una_zona_duplicada_se_rechaza_sin_importar_las_mayusculas(): void
    {
        Zona::create(['nombre' => 'Zona A']);

        $this->actingAs($this->admin())
            ->post('/zonas', ['nombre' => 'zona a', 'activa' => '1'])
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('zonas', 1);
    }

    public function test_renombrar_una_zona_no_choca_con_su_propio_nombre(): void
    {
        $zona = Zona::create(['nombre' => 'Zona A']);

        $this->actingAs($this->admin())
            ->put("/zonas/{$zona->id}", ['nombre' => 'Zona A', 'activa' => '1'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin())
            ->put("/zonas/{$zona->id}", ['nombre' => 'Zona Renombrada', 'activa' => '1'])
            ->assertRedirect(route('zonas.index'));

        $this->assertDatabaseHas('zonas', ['id' => $zona->id, 'nombre' => 'Zona Renombrada']);
    }

    public function test_la_casilla_activo_sin_marcar_desactiva_el_registro(): void
    {
        $zona = Zona::create(['nombre' => 'Zona A', 'activa' => true]);

        $this->actingAs($this->admin())->put("/zonas/{$zona->id}", ['nombre' => 'Zona A']);

        $this->assertDatabaseHas('zonas', ['id' => $zona->id, 'activa' => false]);
    }

    public function test_una_zona_con_lugares_no_se_elimina_pero_una_vacia_si(): void
    {
        $conLugares = Zona::create(['nombre' => 'Con lugares']);
        Lugar::create(['zona_id' => $conLugares->id, 'nombre' => 'L1']);
        $vacia = Zona::create(['nombre' => 'Vacia']);
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/zonas/{$conLugares->id}")->assertSessionHasErrors('general');
        $this->assertDatabaseHas('zonas', ['id' => $conLugares->id]);

        $this->actingAs($admin)->delete("/zonas/{$vacia->id}")->assertRedirect(route('zonas.index'));
        $this->assertDatabaseMissing('zonas', ['id' => $vacia->id]);
    }

    public function test_un_lugar_puede_repetir_nombre_en_otra_zona_pero_no_en_la_misma(): void
    {
        $zona1 = Zona::create(['nombre' => 'Zona 1']);
        $zona2 = Zona::create(['nombre' => 'Zona 2']);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/zonas/{$zona1->id}/lugares", ['nombre' => 'Central', 'activo' => '1'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/zonas/{$zona2->id}/lugares", ['nombre' => 'Central', 'activo' => '1'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/zonas/{$zona1->id}/lugares", ['nombre' => 'Central', 'activo' => '1'])
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('lugares', 2);
    }

    public function test_un_lugar_con_areas_no_se_elimina(): void
    {
        $zona = Zona::create(['nombre' => 'Zona 1']);
        $lugar = Lugar::create(['zona_id' => $zona->id, 'nombre' => 'Lugar 1']);
        Area::create(['lugar_id' => $lugar->id, 'nombre' => 'Area 1']);

        $this->actingAs($this->admin())->delete("/lugares/{$lugar->id}")->assertSessionHasErrors('general');

        $this->assertDatabaseHas('lugares', ['id' => $lugar->id]);
    }

    public function test_un_area_con_impresoras_no_se_elimina_ni_con_borrado_logico(): void
    {
        $zona = Zona::create(['nombre' => 'Zona 1']);
        $lugar = Lugar::create(['zona_id' => $zona->id, 'nombre' => 'Lugar 1']);
        $area = Area::create(['lugar_id' => $lugar->id, 'nombre' => 'Area 1']);
        $impresora = $this->impresoraEn($area, $this->modelo());
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/areas/{$area->id}")->assertSessionHasErrors('general');

        $impresora->delete(); // borrado lógico: la base de datos sigue referenciando el área

        $this->actingAs($admin)->delete("/areas/{$area->id}")->assertSessionHasErrors('general');
        $this->assertDatabaseHas('areas', ['id' => $area->id]);
    }

    public function test_una_marca_duplicada_se_rechaza_y_con_modelos_no_se_elimina(): void
    {
        $modelo = $this->modelo('HP', 'Modelo Alfa');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/marcas', ['nombre' => 'hp'])->assertSessionHasErrors('nombre');
        $this->actingAs($admin)->delete("/marcas/{$modelo->marca_id}")->assertSessionHasErrors('general');

        $this->assertDatabaseHas('marcas', ['id' => $modelo->marca_id]);
    }

    public function test_un_modelo_con_valores_invalidos_se_rechaza(): void
    {
        $marca = Marca::create(['nombre' => 'HP']);
        $admin = $this->admin();
        $base = ['marca_id' => $marca->id, 'nombre' => 'M1', 'tipo_impresion' => 'mono', 'tecnologia' => 'laser'];

        $this->actingAs($admin)->post('/modelos', array_merge($base, ['tipo_impresion' => 'bn']))
            ->assertSessionHasErrors('tipo_impresion');
        $this->actingAs($admin)->post('/modelos', array_merge($base, ['tecnologia' => 'holograma']))
            ->assertSessionHasErrors('tecnologia');
        $this->actingAs($admin)->post('/modelos', array_merge($base, ['marca_id' => 999999]))
            ->assertSessionHasErrors('marca_id');
        $this->actingAs($admin)->post('/modelos', array_merge($base, ['nombre' => '']))
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('modelos_impresora', 0);
    }

    public function test_un_modelo_puede_repetir_nombre_en_otra_marca_pero_no_en_la_misma(): void
    {
        $hp = Marca::create(['nombre' => 'HP']);
        $epson = Marca::create(['nombre' => 'Epson']);
        $admin = $this->admin();
        $datos = ['nombre' => 'Serie 1', 'tipo_impresion' => 'color', 'tecnologia' => 'tinta'];

        $this->actingAs($admin)->post('/modelos', $datos + ['marca_id' => $hp->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/modelos', $datos + ['marca_id' => $epson->id])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post('/modelos', $datos + ['marca_id' => $hp->id])
            ->assertSessionHasErrors('nombre');

        $this->assertDatabaseCount('modelos_impresora', 2);
    }

    public function test_el_soporte_snmp_vacio_se_guarda_como_desconocido(): void
    {
        $marca = Marca::create(['nombre' => 'HP']);

        $this->actingAs($this->admin())->post('/modelos', [
            'marca_id' => $marca->id,
            'nombre' => 'Sin dato',
            'tipo_impresion' => 'mono',
            'tecnologia' => 'laser',
            'snmp_soportado' => '',
        ])->assertSessionHasNoErrors();

        $this->assertNull(ModeloImpresora::where('nombre', 'Sin dato')->firstOrFail()->snmp_soportado);
    }

    public function test_un_modelo_con_impresoras_no_se_elimina(): void
    {
        $zona = Zona::create(['nombre' => 'Zona 1']);
        $lugar = Lugar::create(['zona_id' => $zona->id, 'nombre' => 'Lugar 1']);
        $area = Area::create(['lugar_id' => $lugar->id, 'nombre' => 'Area 1']);
        $modelo = $this->modelo();
        $this->impresoraEn($area, $modelo);

        $this->actingAs($this->admin())->delete("/modelos/{$modelo->id}")->assertSessionHasErrors('general');

        $this->assertDatabaseHas('modelos_impresora', ['id' => $modelo->id]);
    }

    public function test_el_listado_de_modelos_filtra_por_marca(): void
    {
        $this->modelo('HP', 'Modelo Alfa');
        $epson = $this->modelo('Epson', 'Modelo Beta');
        $lector = User::factory()->conRol('consulta')->create();

        $this->actingAs($lector)->get("/modelos?marca={$epson->marca_id}")
            ->assertOk()
            ->assertSee('Modelo Beta')
            ->assertDontSee('Modelo Alfa');
    }

    public function test_el_formulario_de_modelos_se_muestra_para_crear_y_editar(): void
    {
        $modelo = $this->modelo();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/modelos/crear')->assertOk()->assertSee('Nuevo modelo');
        $this->actingAs($admin)->get("/modelos/{$modelo->id}/editar")->assertOk()->assertSee('Editar modelo');
    }
}