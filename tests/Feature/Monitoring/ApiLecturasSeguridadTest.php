<?php

namespace Tests\Feature\Monitoring;

use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Monitoring\Models\Lectura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreaImpresoras;
use Tests\TestCase;

class ApiLecturasSeguridadTest extends TestCase
{
    use CreaImpresoras, RefreshDatabase;

    private const URL = '/api/monitoring/lecturas';

    protected function setUp(): void
    {
        parent::setUp();

        config(['monitoring.token' => 'token-de-prueba']);
    }

    private function cabeceras(): array
    {
        return ['Authorization' => 'Bearer token-de-prueba'];
    }

    private function datos(int $impresoraId, array $extra = []): array
    {
        return array_merge([
            'impresora_id' => $impresoraId,
            'fecha_lectura' => now()->subHour()->format('Y-m-d H:i:s'),
            'origen' => 'snmp',
            'contador_paginas' => 1000,
        ], $extra);
    }

    public function test_sin_token_se_rechaza(): void
    {
        $impresora = $this->crearImpresora();

        $this->postJson(self::URL, $this->datos($impresora->id))->assertUnauthorized();

        $this->assertDatabaseCount('lecturas', 0);
    }

    public function test_un_token_incorrecto_se_rechaza(): void
    {
        $impresora = $this->crearImpresora();

        $this->postJson(self::URL, $this->datos($impresora->id), ['Authorization' => 'Bearer otro'])
            ->assertUnauthorized();

        $this->assertDatabaseCount('lecturas', 0);
    }

    public function test_sin_token_configurado_la_api_rechaza_todo(): void
    {
        $impresora = $this->crearImpresora();
        config(['monitoring.token' => null]);

        $this->postJson(self::URL, $this->datos($impresora->id), $this->cabeceras())->assertUnauthorized();
        $this->postJson(self::URL, $this->datos($impresora->id), ['Authorization' => 'Bearer '])->assertUnauthorized();

        $this->assertDatabaseCount('lecturas', 0);
    }

    public function test_una_ip_publica_se_rechaza_aunque_tenga_el_token(): void
    {
        $impresora = $this->crearImpresora();

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->postJson(self::URL, $this->datos($impresora->id), $this->cabeceras())
            ->assertForbidden();

        $this->assertDatabaseCount('lecturas', 0);
    }

    public function test_la_ruta_tiene_red_local_limite_y_token(): void
    {
        $ruta = collect(Route::getRoutes()->getRoutes())->first(
            fn ($ruta) => $ruta->uri() === 'api/monitoring/lecturas' && in_array('POST', $ruta->methods(), true)
        );

        $this->assertNotNull($ruta);

        foreach (['monitoring.local', 'throttle:monitoreo', 'monitoring.token'] as $middleware) {
            $this->assertContains($middleware, $ruta->gatherMiddleware(), $middleware);
        }

        $this->assertNotNull(RateLimiter::limiter('monitoreo'), 'No se definió el limitador "monitoreo".');
    }

    public function test_registra_una_lectura_valida_con_niveles(): void
    {
        $impresora = $this->crearImpresora();
        $tipo = TipoConsumible::firstOrCreate(['nombre' => 'Toner']);

        $this->postJson(self::URL, $this->datos($impresora->id, [
            'niveles' => [['tipo_consumible_id' => $tipo->id, 'color' => 'negro', 'nivel' => 0.75]],
        ]), $this->cabeceras())
            ->assertCreated()
            ->assertJsonPath('impresora_id', $impresora->id);

        $this->assertDatabaseCount('lecturas', 1);
        $this->assertDatabaseCount('lecturas_nivel', 1);
    }

    public function test_una_fecha_en_utc_se_guarda_en_hora_local(): void
    {
        $impresora = $this->crearImpresora();
        $esperada = now()->subHour();

        $this->postJson(self::URL, $this->datos($impresora->id, [
            'fecha_lectura' => $esperada->copy()->utc()->toIso8601String(),
        ]), $this->cabeceras())->assertCreated();

        $this->assertEqualsWithDelta(
            $esperada->timestamp,
            Lectura::firstOrFail()->fecha_lectura->timestamp,
            5,
            'La fecha enviada en UTC se guardó con desfase.'
        );
    }

    public function test_una_impresora_eliminada_se_rechaza(): void
    {
        $impresora = $this->crearImpresora();
        $impresora->delete();

        $this->postJson(self::URL, $this->datos($impresora->id), $this->cabeceras())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('impresora_id');

        $this->assertDatabaseCount('lecturas', 0);
    }

    public function test_una_fecha_futura_se_rechaza(): void
    {
        $impresora = $this->crearImpresora();

        $this->postJson(self::URL, $this->datos($impresora->id, [
            'fecha_lectura' => now()->addDay()->format('Y-m-d H:i:s'),
        ]), $this->cabeceras())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_lectura');
    }

    public function test_los_niveles_repetidos_se_rechazan(): void
    {
        $impresora = $this->crearImpresora();
        $tipo = TipoConsumible::firstOrCreate(['nombre' => 'Toner']);
        $nivel = ['tipo_consumible_id' => $tipo->id, 'color' => 'negro', 'nivel' => 0.5];

        $this->postJson(self::URL, $this->datos($impresora->id, ['niveles' => [$nivel, $nivel]]), $this->cabeceras())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('niveles');

        $this->assertDatabaseCount('lecturas', 0);
    }

    public function test_una_lectura_duplicada_se_rechaza_con_un_error_de_validacion(): void
    {
        $impresora = $this->crearImpresora();
        $datos = $this->datos($impresora->id);

        $this->postJson(self::URL, $datos, $this->cabeceras())->assertCreated();
        $this->postJson(self::URL, $datos, $this->cabeceras())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fecha_lectura');

        $this->assertDatabaseCount('lecturas', 1);
    }

    public function test_los_errores_de_validacion_son_json_aunque_el_cliente_no_lo_pida(): void
    {
        // Sin Accept: application/json, como lo enviaría un script que no lo configure.
        $this->post(self::URL, [], $this->cabeceras())->assertStatus(422);
    }
}
