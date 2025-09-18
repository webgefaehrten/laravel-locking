<?php

namespace Webgefaehrten\Locking\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLock
{
    /**
     * Sperrt den Datensatz über das Paket-Feature.
     * - Wenn fremd gesperrt: blockt mit 423/Redirect.
     * - Wenn frei oder eigenes Lock: setzt/erneuert Lock inkl. Events.
     */
    public function handle(Request $request, Closure $next, string $param = 'id'): Response
    {
        $model = $request->route($param);

        if ($model && method_exists($model, 'lock')) {
            if (! $model->lock()) {
                $msg = 'Dieser Eintrag ist aktuell gesperrt.';

                return $request->expectsJson()
                    ? response()->json(['status' => $msg], 423)
                    : redirect()->back()->with('status', $msg);
            }
        }

        return $next($request);
    }
}


