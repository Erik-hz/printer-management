<?php

namespace Tests\Feature\Monitoring;

use App\Models\User;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Monitoring\Jobs\ActualizarLecturaSnmp;
use App\Modules\Monitoring\Services\LecturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreaImpresoras;
use Tests\TestCase;

class LecturasTest extends TestCase
{
    use CreaImpresoras, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['monitoring.token' => 'token-de-prueba']);
    }

    public function test_el_servicio_requiere_dato_leido(): void
    {
        $impresora = $this->crearImpresora();
        $usuario = User::factory()->conRol('tecnico')->create();

        try {
            (new LecturaService)->registrar([
                'impresora_id' => $impresora->id,
                'fecha_lectura' => '2026-11-17 18:00:00',
                'origen' => 'manual',
                'usuario_id' => $usuario->id,
            ]);
            $this->fail('Se aceptó una lectura vacía.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('general', $exception->errors());
        }
    }

    public function test_el_servicio_registra_contador_y_niveles(): void
    {
        $impresora = $this->crearImpresora();
        $usuario = User::factory()->conRol('tecnico')->create();
        $tipo = TipoConsumible::firstOrCreate(['nombre' => 'Toner']);

        $lectura = (new LecturaService)->registrar([
            'impresora_id' => $impresora->id,
            'fecha_lectura' => '2026-11-17 18:00:00',
            'origen' => 'manual',
            'contador_paginas' => 12500,
            'usuario_id' => $usuario->id,
            'niveles' => [[
                'tipo_consumible_id' => $tipo->id,
                'color' => 'negro',
                'nivel' => 0.75,
            ]],
        ]);

        $this->assertSame(12500, $lectura->contador_paginas);
        $this->assertDatabaseCount('lecturas_nivel', 1);
    }

    public function test_consulta_ve_listado_y_tecnico_registra_desde_la_pantalla(): void
    {
        $impresora = $this->crearImpresora();
        $tecnico = User::factory()->conRol('tecnico')->create();
        $consulta = User::factory()->conRol('consulta')->create();

        $this->actingAs($consulta)->get('/lecturas')->assertOk();
        $this->actingAs($consulta)->get('/lecturas/crear')->assertForbidden();

        $this->actingAs($tecnico)
            ->post('/lecturas', [
                'impresora_id' => $impresora->id,
                'fecha_lectura' => '2026-11-17T18:00',
                'contador_paginas' => 100,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lecturas', [
            'impresora_id' => $impresora->id,
            'origen' => 'manual',
            'contador_paginas' => 100,
            'usuario_id' => $tecnico->id,
        ]);
    }

    public function test_grafica_muestra_contadores_y_niveles_de_la_impresora_filtrada(): void
    {
        $impresora = $this->crearImpresora();
        $tecnico = User::factory()->conRol('tecnico')->create();
        $tipo = TipoConsumible::firstOrCreate(['nombre' => 'Toner']);

        (new LecturaService)->registrar([
            'impresora_id' => $impresora->id,
            'fecha_lectura' => '2026-11-17 18:00:00',
            'origen' => 'manual',
            'contador_paginas' => 12500,
            'usuario_id' => $tecnico->id,
            'niveles' => [[
                'tipo_consumible_id' => $tipo->id,
                'color' => 'negro',
                'nivel' => 0.75,
            ]],
        ]);

        $this->actingAs($tecnico)
            ->get(route('lecturas.index', ['impresora' => $impresora->id]))
            ->assertOk()
            ->assertSee('role="progressbar"', false)
            ->assertSee('75,0%')
            ->assertViewHas('grafica', fn (array $grafica) => $grafica['contadores'] === [12500]
                && $grafica['niveles'] === [[
                    'etiqueta' => 'Toner Negro',
                    'color' => 'negro',
                    'valores' => [75.0],
                ]]);
    }

    public function test_tecnico_encola_actualizacion_snmp_para_la_impresora_filtrada(): void
    {
        $impresora = $this->crearImpresora();
        $impresora->update([
            'tipo_conexion' => 'red',
            'direccion_ip' => '10.0.0.20',
        ]);
        $tecnico = User::factory()->conRol('tecnico')->create();

        config([
            'queue.default' => 'database',
            'monitoring.api_url' => 'http://localhost/api/monitoring/lecturas',
            'monitoring.snmp_community_get' => 'comunidad-de-prueba',
            'monitoring.snmp_config' => base_path('scripts/printers.snmp.example.json'),
        ]);
        Queue::fake();

        $this->actingAs($tecnico)
            ->post(route('lecturas.actualizar'), ['impresora_id' => $impresora->id])
            ->assertRedirect(route('lecturas.index', ['impresora' => $impresora->id]))
            ->assertSessionHas('estado');

        $this->assertDatabaseHas('ejecuciones_lecturas_snmp', [
            'impresora_id' => $impresora->id,
            'usuario_id' => $tecnico->id,
            'estado' => 'pendiente',
        ]);

        Queue::assertPushed(
            ActualizarLecturaSnmp::class,
            fn (ActualizarLecturaSnmp $job) => $job->ejecucionId > 0
        );
    }

    public function test_tecnico_encola_un_lote_con_todas_las_impresoras_snmp_disponibles(): void
    {
        $disponible = $this->crearImpresora('PRN-DISPONIBLE');
        $disponible->update([
            'tipo_conexion' => 'red',
            'direccion_ip' => '10.0.0.21',
        ]);
        $usb = $this->crearImpresora('PRN-USB');
        $tecnico = User::factory()->conRol('tecnico')->create();

        config([
            'queue.default' => 'database',
            'monitoring.api_url' => 'http://localhost/api/monitoring/lecturas',
            'monitoring.snmp_community_get' => 'comunidad-de-prueba',
            'monitoring.snmp_config' => base_path('scripts/printers.snmp.example.json'),
        ]);
        Queue::fake();

        $respuesta = $this->actingAs($tecnico)
            ->post(route('lecturas.actualizar-todas'))
            ->assertRedirect()
            ->assertSessionHas('estado');

        $this->assertDatabaseCount('ejecuciones_lecturas_snmp', 1);
        $this->assertDatabaseHas('ejecuciones_lecturas_snmp', [
            'impresora_id' => $disponible->id,
            'usuario_id' => $tecnico->id,
            'estado' => 'pendiente',
        ]);
        $this->assertDatabaseMissing('ejecuciones_lecturas_snmp', [
            'impresora_id' => $usb->id,
        ]);
        Queue::assertPushed(ActualizarLecturaSnmp::class, 1);

        parse_str((string) parse_url($respuesta->headers->get('Location'), PHP_URL_QUERY), $parametros);
        $this->assertArrayHasKey('lote', $parametros);

        $this->actingAs($tecnico)
            ->get(route('lecturas.index', ['lote' => $parametros['lote']]))
            ->assertOk()
            ->assertSee('Actualización masiva')
            ->assertSee('PRN-DISPONIBLE');
    }

    public function test_usuario_sin_permiso_no_puede_encolar_actualizacion_snmp(): void
    {
        $impresora = $this->crearImpresora();
        $consulta = User::factory()->conRol('consulta')->create();

        $this->actingAs($consulta)
            ->post(route('lecturas.actualizar'), ['impresora_id' => $impresora->id])
            ->assertForbidden();
    }

    public function test_api_local_registra_snmp_sin_sesion(): void
    {
        $impresora = $this->crearImpresora();

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/monitoring/lecturas', [
                'impresora_id' => $impresora->id,
                'fecha_lectura' => '2026-11-17T18:00:00',
                'origen' => 'snmp',
                'contador_paginas' => 200,
            ], ['Authorization' => 'Bearer token-de-prueba'])
            ->assertCreated()
            ->assertJsonPath('origen', 'snmp');

        $this->assertDatabaseHas('lecturas', [
            'impresora_id' => $impresora->id,
            'origen' => 'snmp',
            'contador_paginas' => 200,
        ]);
    }

    public function test_api_rechaza_ip_publica(): void
    {
        $impresora = $this->crearImpresora();

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->postJson('/api/monitoring/lecturas', [
                'impresora_id' => $impresora->id,
                'fecha_lectura' => '2026-11-17T18:00:00',
                'origen' => 'snmp',
                'contador_paginas' => 200,
            ])
            ->assertForbidden();
    }
}
