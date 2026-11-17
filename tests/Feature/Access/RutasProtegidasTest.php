<?php

namespace Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RutasProtegidasTest extends TestCase
{
    use RefreshDatabase;

    public function test_todas_las_rutas_protegidas_redirigen_al_login_a_un_invitado(): void
    {
        $comprobadas = 0;

        foreach (Route::getRoutes() as $ruta) {
            if (! in_array('auth', $ruta->gatherMiddleware(), true)) {
                continue;
            }

            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $ruta->uri());

            foreach (array_diff($ruta->methods(), ['HEAD']) as $metodo) {
                $this->call($metodo, $uri)->assertRedirect(route('login'));
                $comprobadas++;
            }
        }

        $this->assertGreaterThan(30, $comprobadas, 'Se esperaban más de 30 rutas protegidas.');
    }
}