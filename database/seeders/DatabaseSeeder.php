<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolSeeder::class,
            UsuarioAdminSeeder::class,
            CatalogoImpresorasSeeder::class,
            TiposConsumibleSeeder::class,
            MotivosSinLecturaSeeder::class,
            DatosPruebaSeeder::class,
        ]);
    }
}
