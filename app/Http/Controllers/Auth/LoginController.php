<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $this->trackFailedLogin($request, $credentials['email']);

            return back()
                ->withErrors(['email' => 'El correo o la contraseña están equivocados.'])
                ->onlyInput('email');
        }

        // Login exitoso: limpiamos el contador para que la próxima vez
        // que falle alguien, no arrastremos fallos previos.
        $request->session()->forget(['login.failed_count', 'login.last_error_type']);

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $user->load('role');

        // Panel según rol: `dashboardRouteName()` → `roleSlug()` (tabla `roles` o respaldo por `role_id`).
        $route = $user->dashboardRouteName();
        if ($route === 'login') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Tu cuenta no tiene un rol válido asignado.'])
                ->onlyInput('email');
        }

        return redirect()->intended(route($route));
    }

    /**
     * Lleva un contador en sesión de intentos fallidos consecutivos.
     *
     * Cuando se llega a 2 fallos seguidos, deja un flash `login_help`
     * con el tipo de error (email | password) para que la vista de
     * login abra Lumi automáticamente con un mensaje proactivo.
     *
     * Discriminación email vs password:
     *  - Si existe un User con ese correo → el error es la contraseña.
     *  - Si NO existe → el error es el correo.
     */
    private function trackFailedLogin(Request $request, string $email): void
    {
        $userExists = User::query()->where('email', $email)->exists();
        $type = $userExists ? 'password' : 'email';

        $count = (int) $request->session()->get('login.failed_count', 0) + 1;
        $request->session()->put('login.failed_count', $count);
        $request->session()->put('login.last_error_type', $type);

        if ($count >= 2) {
            // Flash de un solo uso: la vista lo lee y al refrescar desaparece.
            $request->session()->flash('login_help', [
                'type' => $type,
                'email' => $email,
            ]);

            // Reiniciamos el contador para no abrir Lumi en cada fallo
            // posterior; lo volveremos a mostrar tras dos fallos nuevos.
            $request->session()->put('login.failed_count', 0);
        }
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
