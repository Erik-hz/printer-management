<?php

namespace App\Modules\Monitoring\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SoloRedLocal
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        if (! $this->esLocal($ip)) {
            abort(403, 'La API de monitoreo solo acepta conexiones de la red local.');
        }

        return $next($request);
    }

    private function esLocal(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            foreach (['10.', '192.168.'] as $prefijo) {
                if (str_starts_with($ip, $prefijo)) {
                    return true;
                }
            }

            return preg_match('/^172\.(?:1[6-9]|2\d|3[01])\./', $ip) === 1;
        }

        return str_starts_with(strtolower($ip), 'fc') ||
            str_starts_with(strtolower($ip), 'fd') ||
            str_starts_with(strtolower($ip), 'fe8') ||
            str_starts_with(strtolower($ip), 'fe9') ||
            str_starts_with(strtolower($ip), 'fea') ||
            str_starts_with(strtolower($ip), 'feb');
    }
}
