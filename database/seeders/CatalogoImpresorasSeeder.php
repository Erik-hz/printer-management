<?php

namespace Database\Seeders;

use App\Modules\Printers\Models\EstadoImpresora;
use App\Modules\Printers\Models\Marca;
use Illuminate\Database\Seeder;

class CatalogoImpresorasSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['HP', 'Epson', 'Brother'] as $nombre) {
            Marca::firstOrCreate(['nombre' => $nombre]);
        }

        $estados = [
            'activa' => true,
            'retirada' => false,
            'contingencia' => true,
        ];

        foreach ($estados as $nombre => $esOperativo) {
            EstadoImpresora::updateOrCreate(
                ['nombre' => $nombre],
                ['es_operativo' => $esOperativo]
            );
        }
    }
}