<?php

namespace App\Modules;

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
        foreach ($this->modules as $module) {
            $routes = __DIR__ . "/{$module}/routes.php";

            if (is_file($routes)) {
                Route::middleware('web')->group($routes);
            }
        }
    }
}