<?php

namespace Database\Seeders;

use App\Modules\Access\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'administrador' => 'Gestión completa del sistema',
            'responsable_inventario' => 'Administra compras, inventario y movimientos',
            'tecnico' => 'Solicita y consulta insumos; registra lecturas',
            'consulta' => 'Solo lectura',
        ];

        foreach ($roles as $nombre => $descripcion) {
            Rol::updateOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }
    }
}