<?php

namespace App\Modules\Monitoring\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SnapshotOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.snapshot_only') || $request->routeIs('snapshot.index')) {
            return $next($request);
        }

        if ($request->isMethodSafe()) {
            return redirect()->route('snapshot.index');
        }

        abort(404);
    }
}
