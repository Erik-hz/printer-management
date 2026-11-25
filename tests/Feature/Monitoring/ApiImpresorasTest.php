<?php

namespace Tests\Feature\Monitoring;

use App\Modules\Printers\Models\Impresora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreaImpresoras;
use Tests\TestCase;

class ApiImpresorasTest extends TestCase
{
    use CreaImpresoras, RefreshDatabase;

    private const URL = '/api/monitoring/impresoras';

    protected function setUp(): void
    {
        parent::setUp();

        config(['monitoring.token' => 'token-de-prueba']);
    }

    public function test_devuelve_impresoras_de_red_operativas_con_snmp_no_descartado_e_ip_privada(): void
    {
        $elegible = $this->crearImpresora('PRN-SNMP');
        $elegible->update(['tipo_conexion' => 'red', 'direccion_ip' => '172.20.38.13']);

        $usb = $this->crearImpresora('PRN-USB');

        $retirada = $this->crearImpresora('PRN-RETIRADA', 'retirada');
        $retirada->update(['tipo_conexion' => 'red', 'direccion_ip' => '10.0.0.3']);

        $eliminada = $this->crearImpresora('PRN-ELIMINADA');
        $eliminada->update(['tipo_conexion' => 'red', 'direccion_ip' => '10.0.0.5']);
        $eliminada->delete();

        $modeloNoSnmp = $elegible->modelo->replicate();
        $modeloNoSnmp->nombre = 'Modelo sin SNMP';
        $modeloNoSnmp->snmp_soportado = false;
        $modeloNoSnmp->save();

        $sinSnmp = Impresora::create([
            'nombre' => 'PRN-SIN-SNMP',
            'modelo_id' => $modeloNoSnmp->id,
            'area_id' => $elegible->area_id,
            'estado_id' => $elegible->estado_id,
            'tipo_conexion' => 'red',
            'direccion_ip' => '10.0.0.4',
        ]);

        $ipNoPrivada = Impresora::create([
            'nombre' => 'PRN-IP-NO-PRIVADA',
            'modelo_id' => $elegible->modelo_id,
            'area_id' => $elegible->area_id,
            'estado_id' => $elegible->estado_id,
            'tipo_conexion' => 'red',
            'direccion_ip' => '192.0.2.10',
        ]);

        $respuesta = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson(self::URL, ['Authorization' => 'Bearer token-de-prueba'])
            ->assertOk()
            ->assertJsonCount(1, 'printers')
            ->assertJsonPath('printers.0.printer_id', $elegible->id)
            ->assertJsonPath('printers.0.ip', '172.20.38.13')
            ->assertJsonPath('printers.0.port', 161);

        $this->assertNotSame($usb->id, $respuesta->json('printers.0.printer_id'));
        $this->assertNotSame($retirada->id, $respuesta->json('printers.0.printer_id'));
        $this->assertNotSame($eliminada->id, $respuesta->json('printers.0.printer_id'));
        $this->assertNotSame($sinSnmp->id, $respuesta->json('printers.0.printer_id'));
        $this->assertNotSame($ipNoPrivada->id, $respuesta->json('printers.0.printer_id'));
    }

    public function test_permite_filtrar_por_id_registrado(): void
    {
        $impresora = $this->crearImpresora('PRN-SNMP');
        $impresora->update(['tipo_conexion' => 'red', 'direccion_ip' => '10.0.0.2']);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson(self::URL.'?printer_id='.$impresora->id, ['Authorization' => 'Bearer token-de-prueba'])
            ->assertOk()
            ->assertJsonCount(1, 'printers')
            ->assertJsonPath('printers.0.printer_id', $impresora->id);
    }

    public function test_el_listado_requiere_token_y_red_local(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->getJson(self::URL)
            ->assertUnauthorized();

        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->getJson(self::URL, ['Authorization' => 'Bearer token-de-prueba'])
            ->assertForbidden();

        $ruta = collect(Route::getRoutes()->getRoutes())->first(
            fn ($ruta) => $ruta->uri() === 'api/monitoring/impresoras' && in_array('GET', $ruta->methods(), true)
        );

        $this->assertNotNull($ruta);
        foreach (['monitoring.local', 'throttle:monitoreo', 'monitoring.token'] as $middleware) {
            $this->assertContains($middleware, $ruta->gatherMiddleware(), $middleware);
        }
    }
}
