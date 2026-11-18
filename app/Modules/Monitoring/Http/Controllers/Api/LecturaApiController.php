<?php

namespace App\Modules\Monitoring\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Monitoring\Services\LecturaService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LecturaApiController extends Controller
{
    public function __construct(private readonly LecturaService $lecturas) {}

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'impresora_id' => [
                'required',
                'integer',
                Rule::exists('impresoras', 'id')->whereNull('deleted_at'),
            ],
            // Se admiten 5 minutos de diferencia de reloj; más allá, la fecha es futura.
            'fecha_lectura' => ['required', 'date', 'before_or_equal:+5 minutes'],
            'origen' => ['required', Rule::in(['snmp', 'powershell'])],
            'contador_paginas' => ['nullable', 'integer', 'min:0'],
            'motivo_sin_lectura_id' => [
                'nullable',
                'prohibited_with:contador_paginas',
                'exists:motivos_sin_lectura,id',
            ],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'niveles' => [
                'nullable',
                'array',
                'max:20',
                function (string $atributo, mixed $valor, \Closure $fallar): void {
                    $claves = collect($valor)->map(
                        fn ($nivel) => ($nivel['tipo_consumible_id'] ?? '').'|'.($nivel['color'] ?? '')
                    );

                    if ($claves->count() !== $claves->unique()->count()) {
                        $fallar('Hay niveles repetidos para el mismo tipo de consumible y color.');
                    }
                },
            ],
            'niveles.*.tipo_consumible_id' => ['required', 'integer', 'exists:tipos_consumible,id'],
            'niveles.*.color' => [
                'required',
                Rule::in(['negro', 'cian', 'magenta', 'amarillo', 'tricolor', 'no_aplica']),
            ],
            'niveles.*.nivel' => ['required', 'numeric', 'between:0,1'],
        ]);

        $niveles = $datos['niveles'] ?? [];

        if (($datos['contador_paginas'] ?? null) === null &&
            ($datos['motivo_sin_lectura_id'] ?? null) === null &&
            $niveles === []) {
            throw ValidationException::withMessages([
                'general' => 'La lectura debe incluir un contador, niveles o un motivo sin lectura.',
            ]);
        }

        try {
            $lectura = $this->lecturas->registrar($datos);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Ya existe una lectura de esa impresora con la misma fecha y origen.',
            ], 409);
        }

        return response()->json([
            'id' => $lectura->id,
            'impresora_id' => $lectura->impresora_id,
            'fecha_lectura' => $lectura->fecha_lectura->toIso8601String(),
            'origen' => $lectura->origen,
        ], 201);
    }
}
