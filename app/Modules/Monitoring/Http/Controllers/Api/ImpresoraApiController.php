<?php

namespace App\Modules\Monitoring\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Services\ImpresorasDisponiblesSnmp;
use App\Modules\Printers\Models\Impresora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpresoraApiController extends Controller
{
    public function __construct(private readonly ImpresorasDisponiblesSnmp $impresoras) {}

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'printer_id' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);

        $impresoras = $this->impresoras->listar(isset($datos['printer_id']) ? (int) $datos['printer_id'] : null)
            ->map(fn (Impresora $impresora) => [
                'printer_id' => $impresora->id,
                'ip' => $impresora->direccion_ip,
                'port' => 161,
            ]);

        return response()->json(['printers' => $impresoras]);
    }
}
