<?php

namespace App\Modules\Monitoring\Services;

use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Services\PrinterConnectionService;
use Illuminate\Support\Collection;

class ImpresorasActivasSnmp
{
    public function __construct(
        private readonly ImpresorasDisponiblesSnmp $disponibles,
        private readonly PrinterConnectionService $conectividad,
    ) {}

    /** @return array{impresoras: Collection<int, Impresora>, elegibles: int, sin_respuesta: int, errores: list<string>, versiones: array<int, string>} */
    public function listar(?int $printerId = null, int $timeout = 2): array
    {
        $elegibles = $this->disponibles->listar($printerId);
        $sinRespuesta = 0;
        $errores = [];
        $versiones = [];
        $activas = $elegibles->filter(function (Impresora $impresora) use ($timeout, &$sinRespuesta, &$errores, &$versiones): bool {
            $resultado = $this->conectividad->checkSnmp((string) $impresora->direccion_ip, $timeout);
            if (! $resultado['available']) {
                $sinRespuesta++;
                $errores[] = "{$impresora->nombre}: ".($resultado['error'] ?? 'sin respuesta SNMP');

                return false;
            }

            $versiones[$impresora->id] = $resultado['version'];

            return true;
        })->values();

        return [
            'impresoras' => $activas,
            'elegibles' => $elegibles->count(),
            'sin_respuesta' => $sinRespuesta,
            'errores' => $errores,
            'versiones' => $versiones,
        ];
    }
}
