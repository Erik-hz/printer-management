<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Purchasing\Models\PrecioConsumible;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrecioConsumibleService
{
    public function registrar(array $attributes): PrecioConsumible
    {
        $desde = $this->fecha($attributes['vigente_desde']);
        $hasta = isset($attributes['vigente_hasta'])
            ? $this->fecha($attributes['vigente_hasta'])
            : null;

        if ($hasta !== null && $hasta->lt($desde)) {
            throw ValidationException::withMessages([
                'vigente_hasta' => 'La vigencia final no puede ser anterior a la inicial.',
            ]);
        }

        $this->rechazarSolapamiento(
            (int) $attributes['consumible_id'],
            array_key_exists('proveedor_id', $attributes) ? $attributes['proveedor_id'] : null,
            $desde,
            $hasta
        );

        return PrecioConsumible::query()->create([
            ...$attributes,
            'vigente_desde' => $desde->toDateString(),
            'vigente_hasta' => $hasta?->toDateString(),
        ]);
    }

    public function cerrarVigencia(PrecioConsumible $precio, string $vigenteHasta): PrecioConsumible
    {
        $hasta = $this->fecha($vigenteHasta);

        if ($hasta->lt($precio->vigente_desde)) {
            throw ValidationException::withMessages([
                'vigente_hasta' => 'La vigencia final no puede ser anterior a la inicial.',
            ]);
        }

        return DB::transaction(function () use ($precio, $hasta): PrecioConsumible {
            $precioBloqueado = PrecioConsumible::query()->lockForUpdate()->findOrFail($precio->id);

            if ($precioBloqueado->vigente_hasta !== null) {
                throw ValidationException::withMessages([
                    'vigente_hasta' => 'Este precio ya tiene una fecha de fin de vigencia.',
                ]);
            }

            $this->rechazarSolapamiento(
                (int) $precioBloqueado->consumible_id,
                $precioBloqueado->proveedor_id,
                $this->fecha($precioBloqueado->vigente_desde->toDateString()),
                $hasta,
                (int) $precioBloqueado->id,
            );

            $precioBloqueado->vigente_hasta = $hasta->toDateString();
            $precioBloqueado->save();

            return $precioBloqueado;
        });
    }

    private function rechazarSolapamiento(
        int $consumibleId,
        ?int $proveedorId,
        CarbonImmutable $desde,
        ?CarbonImmutable $hasta,
        ?int $exceptoPrecioId = null,
    ): void {
        $solapa = PrecioConsumible::query()
            ->where('consumible_id', $consumibleId)
            ->when($exceptoPrecioId !== null, fn ($consulta) => $consulta->whereKeyNot($exceptoPrecioId))
            ->when(
                $proveedorId === null,
                fn ($consulta) => $consulta->whereNull('proveedor_id'),
                fn ($consulta) => $consulta->where('proveedor_id', $proveedorId)
            )
            ->where('vigente_desde', '<=', $hasta?->toDateString() ?? '9999-12-31')
            ->where(function ($consulta) use ($desde): void {
                $consulta
                    ->whereNull('vigente_hasta')
                    ->orWhere('vigente_hasta', '>=', $desde->toDateString());
            })
            ->exists();

        if ($solapa) {
            throw ValidationException::withMessages([
                'vigente_desde' => 'Ya existe un precio vigente para este consumible y proveedor en el periodo indicado.',
            ]);
        }
    }

    private function fecha(string $valor): CarbonImmutable
    {
        return CarbonImmutable::parse($valor)->startOfDay();
    }
}
