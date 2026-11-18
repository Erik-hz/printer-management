<?php

namespace App\Modules;

use App\Modules\Access\Models\Permisos;
use App\Modules\Inventory\Console\VerificarInventario;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModulesServiceProvider extends ServiceProvider
{
    /**
     * Módulos del sistema. Cada uno vive en app/Modules/{Nombre}.
     */
    protected array $modules = [
        'Printers',
        'Inventory',
        'Monitoring',
        'Purchasing',
        'Reports',
        'Imports',
        'Access',
    ];

    public function boot(): void
    {
        Permisos::registrar();
        $this->commands([VerificarInventario::class]);

        foreach ($this->modules as $module) {
            $routes = __DIR__."/{$module}/routes.php";

            if (is_file($routes)) {
                Route::middleware('web')->group($routes);
            }
        }
    }
}
