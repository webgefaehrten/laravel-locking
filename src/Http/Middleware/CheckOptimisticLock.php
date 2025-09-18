<?php

namespace Webgefaehrten\Locking\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Carbon;

class CheckOptimisticLock
{
    /**
     * Validiert vor schreibenden Aktionen, dass das Model seit dem Laden unverändert ist.
     * Erwartet, dass das Formular ein Hidden-Feld mit dem geladenen `updated_at`-Wert sendet.
     *
     * Standard-Parametername ist der Route-Parameter des Models, z. B. `post` in `/posts/{post}`.
     * Optional kann ein eigener Request-Feldname übergeben werden (z. B. `version`),
     * ansonsten wird `${param}_version` erwartet (z. B. `post_version`).
     */
    public function handle(Request $request, Closure $next, string $param = 'id', ?string $field = null): Response
    {
        $model = $request->route($param);

        if ($model) {
            $updatedAtColumn = $model->getUpdatedAtColumn();

            // Feldname ermitteln: explizit übergeben oder <param>_version
            $versionField = $field ?: ($param . '_version');
            $provided = $request->input($versionField);

            $current = $model->newQueryWithoutScopes()
                ->whereKey($model->getKey())
                ->value($updatedAtColumn);

            if ($current && $provided) {
                $currentCarbon = $current instanceof Carbon
                    ? $current
                    : Carbon::parse((string) $current);
                $providedCarbon = $provided instanceof Carbon
                    ? $provided
                    : Carbon::parse((string) $provided);

                if ($currentCarbon->ne($providedCarbon)) {
                    $msg = 'Der Datensatz wurde inzwischen geändert. Bitte Seite neu laden.';

                    return $request->expectsJson()
                        ? response()->json(['status' => $msg], 409)
                        : redirect()->back()->withInput()->with('status', $msg);
                }
            } elseif ($current && ! $provided) {
                $msg = 'Der Datensatz wurde inzwischen geändert. Bitte Seite neu laden.';
                return $request->expectsJson()
                    ? response()->json(['status' => $msg], 409)
                    : redirect()->back()->withInput()->with('status', $msg);
            }
        }

        return $next($request);
    }
}


