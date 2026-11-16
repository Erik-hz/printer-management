<?php

namespace Database\Seeders;

use App\Modules\Inventory\Models\TipoConsumible;
use Illuminate\Database\Seeder;

class TiposConsumibleSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'Tóner',
            'Kit de mantenimiento',
            'Fusor',
            'Unidad de transferencia',
            'Tinta',
            'Cartucho de plotter',
            'Papel',
        ];

        foreach ($tipos as $nombre) {
            TipoConsumible::firstOrCreate(['nombre' => $nombre]);
        }
    }
}