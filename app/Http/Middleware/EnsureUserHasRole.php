<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $user->loadMissing('role');
        $name = $user->role?->name;

        if (! $name || ! in_array($name, $roles, true)) {
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
