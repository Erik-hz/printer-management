<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Consumible;
use Illuminate\Support\Facades\DB;

class ConsumibleService
{
    public function crear(array $attributes): Consumible
    {
        return DB::transaction(function () use ($attributes): Consumible {
            $consumible = Consumible::query()->create($attributes);
            $consumible->existencia()->create(['cantidad' => 0]);

            return $consumible;
        });
    }
}
