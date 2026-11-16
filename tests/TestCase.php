<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $conexion = $app['config']->get('database.default');
        $base = $app['config']->get("database.connections.{$conexion}.database");

        if ($conexion !== 'sqlsrv' || $base !== 'laravel_test') {
            throw new RuntimeException(
                "Las pruebas solo pueden ejecutarse contra la base 'laravel_test' (sqlsrv). "
                ."Configuración activa: {$conexion} / {$base}."
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Las pruebas no necesitan los estilos compilados por Vite.
        $this->withoutVite();
    }
}