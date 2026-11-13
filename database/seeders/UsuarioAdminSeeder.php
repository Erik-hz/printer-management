<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Access\Models\Rol;
use Illuminate\Database\Seeder;

class UsuarioAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $rol = Rol::where('nombre', 'administrador')->firstOrFail();

        $usuario = User::firstOrNew(['email' => 'admin@sgiic.test']);
        $usuario->forceFill([
            'name' => 'Administrador (desarrollo)',
            'password' => 'Dev-Sgiic-2026',
            'rol_id' => $rol->id,
            'activo' => true,
        ])->save();
    }
}