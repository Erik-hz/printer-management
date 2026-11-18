<?php

namespace App\Modules\Monitoring\Services;

use App\Modules\Monitoring\Models\Lectura;
use App\Modules\Printers\Models\Impresora;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LecturaService
{
    public function registrar(array $attributes): Lectura
    {
        $niveles = $attributes['niveles'] ?? [];
        unset($attributes['niveles']);

        $contador = $attributes['contador_paginas'] ?? null;
        $motivo = $attributes['motivo_sin_lectura_id'] ?? null;

        if ($contador === null && $motivo === null && $niveles === []) {
            throw ValidationException::withMessages([
                'general' => 'La lectura debe incluir un contador, niveles o un motivo sin lectura.',
            ]);
        }

        if ($contador !== null && $motivo !== null) {
            throw ValidationException::withMessages([
                'motivo_sin_lectura_id' => 'No se puede informar un contador y un motivo a la vez.',
            ]);
        }

        $fecha = CarbonImmutable::parse($attributes['fecha_lectura']);
        $attributes['fecha_lectura'] = $fecha;

        if ($attributes['origen'] === 'manual' && empty($attributes['usuario_id'])) {
            throw ValidationException::withMessages([
                'usuario_id' => 'Las lecturas manuales requieren el usuario que las registra.',
            ]);
        }

        if (! Impresora::query()->withTrashed()->whereKey($attributes['impresora_id'])->exists()) {
            throw ValidationException::withMessages([
                'impresora_id' => 'La impresora seleccionada no existe.',
            ]);
        }

        return DB::transaction(function () use ($attributes, $niveles): Lectura {
            $duplicada = Lectura::query()
                ->where('impresora_id', $attributes['impresora_id'])
                ->where('fecha_lectura', $attributes['fecha_lectura'])
                ->where('origen', $attributes['origen'])
                ->exists();

            if ($duplicada) {
                throw ValidationException::withMessages([
                    'fecha_lectura' => 'Ya existe una lectura de este origen para la impresora en esa fecha.',
                ]);
            }

            $lectura = Lectura::query()->create($attributes);

            foreach ($niveles as $nivel) {
                $lectura->niveles()->create([
                    'tipo_consumible_id' => $nivel['tipo_consumible_id'],
                    'color' => $nivel['color'],
                    'nivel' => $nivel['nivel'],
                ]);
            }

            return $lectura->load('niveles');
        });
    }
}
