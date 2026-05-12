<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe la ruta a uno o más roles (`role:admin`, `role:coordinator`, …).
 * Compara contra `User::roleSlug()` (nombre en `roles` o respaldo por `role_id`), igual que tras el login.
 */
class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     * @param  string  ...$roles  Slugs permitidos: `admin`, `coordinator`, `instructor` (como en la ruta).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Mismo criterio que `User::dashboardRouteName()` para no desincronizar login y permisos.
        $slug = $user->roleSlug();

        // Sin rol reconocido o rol no listado en la ruta: redirige al panel que sí le corresponde o al login.
        if (! $slug || ! in_array($slug, $roles, true)) {
            $target = $user->dashboardRouteName();
            if ($target === 'login') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Tu cuenta no tiene un rol válido asignado.',
                ]);
            }

            return redirect()->route($target);
        }

        return $next($request);
    }
}
