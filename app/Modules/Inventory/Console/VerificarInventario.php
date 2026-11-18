<?php

namespace App\Modules\Inventory\Console;

use App\Modules\Inventory\Models\Consumible;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerificarInventario extends Command
{
    protected $signature = 'inventario:verificar';

    protected $description = 'Compara las existencias actuales con la suma de los movimientos.';

    public function handle(): int
    {
        $consumibles = Consumible::withTrashed()
            ->with('existencia')
            ->orderBy('codigo')
            ->get();

        $sumas = DB::table('movimientos_inventario')
            ->select('consumible_id')
            ->selectRaw('SUM(CAST(cantidad AS bigint)) AS total')
            ->groupBy('consumible_id')
            ->pluck('total', 'consumible_id')
            ->map(fn ($total) => (int) $total);

        $diferencias = 0;

        foreach ($consumibles as $consumible) {
            $actual = $consumible->existencia === null
                ? null
                : (int) $consumible->existencia->cantidad;
            $porMovimientos = (int) ($sumas[(int) $consumible->id] ?? 0);

            if ($actual === null) {
                $this->error("{$consumible->codigo}: falta la fila de existencias.");
                $diferencias++;

                continue;
            }

            if ($actual !== $porMovimientos) {
                $this->error(
                    "{$consumible->codigo}: existencia {$actual}, suma de movimientos {$porMovimientos}."
                );
                $diferencias++;
            }
        }

        if ($diferencias > 0) {
            $this->error("Se encontraron {$diferencias} diferencias en el inventario.");

            return self::FAILURE;
        }

        $this->info('Las existencias coinciden con la suma de movimientos.');

        return self::SUCCESS;
    }
}
