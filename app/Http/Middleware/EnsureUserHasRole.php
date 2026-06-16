<?php

namespace App\Http\Middleware;

use App\Models\Instructor;
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

        // Si el rol es instructor, verificar que su cuenta no esté suspendida/bloqueada.
        if ($slug === 'instructor') {
            $instructor = Instructor::query()
                ->where('user_id', $user->id)
                ->value('status');

            if ($instructor !== null && in_array($instructor, Instructor::BLOCKED_STATUSES, true)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $message = match ($instructor) {
                    Instructor::STATUS_SUSPENDED =>
                        'Tu cuenta de instructor está suspendida temporalmente. Comunícate con tu coordinador para más información.',
                    Instructor::STATUS_BLOCKED =>
                        'Tu cuenta de instructor ha sido inhabilitada. Comunícate con la coordinación para más información.',
                    default =>
                        'Tu cuenta de instructor no está activa. Comunícate con tu coordinador.',
                };

                return redirect()->route('login')->withErrors(['email' => $message]);
            }
        }

        return $next($request);
    }
}
