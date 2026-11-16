<?php

namespace Database\Seeders;

use App\Modules\Monitoring\Models\MotivoSinLectura;
use Illuminate\Database\Seeder;

class MotivosSinLecturaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['retirado', 'descuento'] as $nombre) {
            MotivoSinLectura::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
