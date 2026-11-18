<?php

namespace App\Modules\Monitoring\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Exige el token compartido en "Authorization: Bearer ...". Sin token configurado, rechaza todo. */
class TokenMonitoreo
{
    public function handle(Request $request, Closure $next): Response
    {
        $esperado = (string) config('monitoring.token');
        $recibido = (string) $request->bearerToken();

        if ($esperado === '' || ! hash_equals($esperado, $recibido)) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        return $next($request);
    }
}