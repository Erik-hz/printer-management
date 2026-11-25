<?php

use App\Modules\Access\Http\Middleware\UsuarioActivo;
use App\Modules\Monitoring\Http\Middleware\SoloRedLocal;
use App\Modules\Monitoring\Http\Middleware\SnapshotOnly;
use App\Modules\Monitoring\Http\Middleware\TokenMonitoreo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\CheckPrinterConnection::class,
        \App\Console\Commands\ImportarSnapshotMonitoreo::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [UsuarioActivo::class, SnapshotOnly::class]);
        $middleware->api(append: [SnapshotOnly::class]);
        $middleware->alias([
            'monitoring.local' => SoloRedLocal::class,
            'monitoring.token' => TokenMonitoreo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Las rutas de la API siempre responden JSON, aunque el cliente no envíe Accept: application/json.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
