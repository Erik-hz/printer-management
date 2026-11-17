<?php

namespace App\Modules\Printers\Services;

use App\Modules\Printers\Models\EstadoImpresora;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Support\DireccionIp;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class ImpresoraService
{
    public function crear(array $datos): Impresora
    {
        return $this->guardar(fn () => Impresora::create($this->normalizar($datos)));
    }

    public function actualizar(Impresora $impresora, array $datos): Impresora
    {
        return $this->guardar(function () use ($impresora, $datos) {
            $impresora->update($this->normalizar($datos));

            return $impresora;
        });
    }

    /** Cambia el estado a "retirada"; la impresora se conserva con su historial. */
    public function retirar(Impresora $impresora): Impresora
    {
        $retirada = EstadoImpresora::where('nombre', 'retirada')->firstOrFail();

        $impresora->update(['estado_id' => $retirada->id]);

        return $impresora;
    }

    /** Borrado lógico, pensado para corregir errores de carga. */
    public function eliminar(Impresora $impresora): void
    {
        $impresora->delete();
    }

    private function normalizar(array $datos): array
    {
        if (array_key_exists('direccion_ip', $datos)) {
            $datos['direccion_ip'] = DireccionIp::normalizar($datos['direccion_ip']);
        }

        return $datos;
    }

    private function guardar(callable $operacion): Impresora
    {
        try {
            return $operacion();
        } catch (UniqueConstraintViolationException) {
            // Respaldo ante una carrera: los índices únicos filtrados de la base de datos.
            throw ValidationException::withMessages([
                'general' => 'Ya existe otra impresora con ese nombre o esa dirección IP.',
            ]);
        }
    }
}