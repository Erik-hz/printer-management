<?php

namespace Tests\Feature\Monitoring;

use App\Models\User;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Monitoring\Services\LecturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreaImpresoras;
use Tests\TestCase;

class LecturasTest extends TestCase
{
    use CreaImpresoras, RefreshDatabase;

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

    public function test_api_local_registra_snmp_sin_sesion(): void
    {
        $impresora = $this->crearImpresora();

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/monitoring/lecturas', [
                'impresora_id' => $impresora->id,
                'fecha_lectura' => '2026-11-17T18:00:00',
                'origen' => 'snmp',
                'contador_paginas' => 200,
            ])
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
