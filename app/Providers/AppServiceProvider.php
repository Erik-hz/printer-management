<?php

namespace App\Providers;

use App\Database\Connectors\PinnedSqlServerConnector;
use Illuminate\Database\SqlServerConnection;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::extend('sqlsrv', function (array $config): SqlServerConnection {
            $pdo = (new PinnedSqlServerConnector())->connect($config);

            return new SqlServerConnection($pdo, $config['database'], $config['prefix'], $config);
        });

        Paginator::useBootstrapFive();
        RateLimiter::for('monitoreo', fn (Request $request) => Limit::perMinute(300)->by((string) $request->ip()));
    }
}
