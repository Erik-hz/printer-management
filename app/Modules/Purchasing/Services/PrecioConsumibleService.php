<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Inventory\Models\Consumible;
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

        return DB::transaction(function () use ($attributes, $desde, $hasta): PrecioConsumible {
            $consumibleId = (int) $attributes['consumible_id'];
            $this->bloquearPreciosDelConsumible($consumibleId);

            $this->rechazarSolapamiento(
                $consumibleId,
                array_key_exists('proveedor_id', $attributes) ? $attributes['proveedor_id'] : null,
                $desde,
                $hasta
            );

            return PrecioConsumible::query()->create([
                ...$attributes,
                'vigente_desde' => $desde->toDateString(),
                'vigente_hasta' => $hasta?->toDateString(),
            ]);
        });
    }

    public function cerrarVigencia(PrecioConsumible $precio, string $vigenteHasta): PrecioConsumible
    {
        $hasta = $this->fecha($vigenteHasta);

        return DB::transaction(function () use ($precio, $hasta): PrecioConsumible {
            $consumibleId = (int) $precio->consumible_id;
            $this->bloquearPreciosDelConsumible($consumibleId);
            $precioBloqueado = PrecioConsumible::query()->lockForUpdate()->findOrFail($precio->id);

            if ((int) $precioBloqueado->consumible_id !== $consumibleId) {
                throw ValidationException::withMessages([
                    'vigente_hasta' => 'El precio cambió durante la operación. Vuelva a intentarlo.',
                ]);
            }

            if ($precioBloqueado->vigente_hasta !== null) {
                throw ValidationException::withMessages([
                    'vigente_hasta' => 'Este precio ya tiene una fecha de fin de vigencia.',
                ]);
            }

            $desde = $this->fecha($precioBloqueado->vigente_desde->toDateString());

            if ($hasta->lt($desde)) {
                throw ValidationException::withMessages([
                    'vigente_hasta' => 'La vigencia final no puede ser anterior a la inicial.',
                ]);
            }

            $this->rechazarSolapamiento(
                $consumibleId,
                $precioBloqueado->proveedor_id,
                $desde,
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

    private function bloquearPreciosDelConsumible(int $consumibleId): void
    {
        // Lock the parent row so overlapping inserts serialize even when no price row exists yet.
        Consumible::query()->lockForUpdate()->findOrFail($consumibleId);
    }

    private function fecha(string $valor): CarbonImmutable
    {
        return CarbonImmutable::parse($valor)->startOfDay();
    }
}
