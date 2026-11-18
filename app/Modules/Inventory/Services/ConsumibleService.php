<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Consumible;
use App\Modules\Purchasing\Models\DetalleCompra;
use App\Modules\Purchasing\Models\PrecioConsumible;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumibleService
{
    /**
     * Crea el consumible con su existencia en cero y su compatibilidad.
     * La clave "modelos" es la lista de ids de modelos compatibles.
     */
    public function crear(array $attributes): Consumible
    {
        $modelos = $attributes['modelos'] ?? [];
        unset($attributes['modelos']);

        return DB::transaction(function () use ($attributes, $modelos): Consumible {
            $consumible = Consumible::query()->create($attributes);
            $consumible->existencia()->create(['cantidad' => 0]);
            $consumible->modelos()->sync($modelos);

            return $consumible;
        });
    }

    /**
     * Actualiza el consumible y reemplaza su compatibilidad por la lista indicada.
     * Si "modelos" no viene, el consumible queda sin modelos compatibles.
     * No toca las existencias: solo cambian con movimientos.
     */
    public function actualizar(Consumible $consumible, array $attributes): Consumible
    {
        $modelos = $attributes['modelos'] ?? [];
        unset($attributes['modelos']);

        return DB::transaction(function () use ($consumible, $attributes, $modelos): Consumible {
            $consumible->update($attributes);
            $consumible->modelos()->sync($modelos);

            return $consumible;
        });
    }

    /** Borrado lógico, solo si nada depende del consumible. Lo normal es desactivarlo. */
    public function eliminar(Consumible $consumible): void
    {
        $tieneMovimientos = $consumible->movimientosInventario()->exists();
        $tieneCompras = DetalleCompra::query()->where('consumible_id', $consumible->id)->exists();
        $tienePrecios = PrecioConsumible::query()->where('consumible_id', $consumible->id)->exists();

        if ($tieneMovimientos || $tieneCompras || $tienePrecios) {
            throw ValidationException::withMessages([
                'general' => 'No se puede eliminar el consumible porque tiene movimientos, compras o precios registrados. Desactívelo en su lugar.',
            ]);
        }

        DB::transaction(function () use ($consumible): void {
            // La compatibilidad se desvincula para no bloquear después el borrado de modelos.
            $consumible->modelos()->detach();
            $consumible->delete();
        });
    }
}