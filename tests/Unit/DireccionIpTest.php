<?php

namespace Tests\Unit;

use App\Modules\Printers\Support\DireccionIp;
use PHPUnit\Framework\TestCase;

class DireccionIpTest extends TestCase
{
    public function test_quita_los_ceros_a_la_izquierda_de_cada_octeto(): void
    {
        $this->assertSame('172.20.15.25', DireccionIp::normalizar('172.20.015.25'));
        $this->assertSame('10.1.0.9', DireccionIp::normalizar('010.001.000.009'));
    }

    public function test_recorta_espacios_y_los_vacios_son_nulos(): void
    {
        $this->assertSame('10.0.0.1', DireccionIp::normalizar('  10.0.0.1 '));
        $this->assertNull(DireccionIp::normalizar('   '));
        $this->assertNull(DireccionIp::normalizar(''));
        $this->assertNull(DireccionIp::normalizar(null));
    }

    public function test_lo_que_no_parece_una_ip_se_devuelve_intacto(): void
    {
        $this->assertSame('172.20.15.-', DireccionIp::normalizar('172.20.15.-'));
        $this->assertSame('USB', DireccionIp::normalizar('USB'));
        $this->assertSame('192.168.1', DireccionIp::normalizar('192.168.1'));
    }

    public function test_acepta_ipv4_validas(): void
    {
        $this->assertTrue(DireccionIp::esValida('192.168.1.10'));
        $this->assertTrue(DireccionIp::esValida('172.20.15.25'));
    }

    public function test_rechaza_valores_que_no_son_ipv4(): void
    {
        $this->assertFalse(DireccionIp::esValida('999.1.1.1'));
        $this->assertFalse(DireccionIp::esValida('172.20.015.25')); // sin normalizar
        $this->assertFalse(DireccionIp::esValida('USB'));
        $this->assertFalse(DireccionIp::esValida(null));
    }
}