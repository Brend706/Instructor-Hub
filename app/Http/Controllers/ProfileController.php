<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Perfil del usuario autenticado: ver datos, actualizar nombre en `users` y cambiar contraseña.
 * Los datos de sesión provienen de la tabla `users` (correo solo lectura en la vista).
 */
class ProfileController extends Controller
{
    /**
     * Muestra la página "Mi perfil" con resumen lateral y formularios.
     * Pasa fechas formateadas en español y breadcrumbs hacia el inicio según el rol.
     */
    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $user->loadMissing('role');

        // "Miembro desde": mes y año según `users.created_at` (locale es, formato legible).
        $memberSince = '—';
        if ($user->created_at) {
            // Carbon `isoFormat`: meses traducidos; texto fijo entre corchetes, p. ej. [de].
            $memberSince = ucfirst($user->created_at->locale('es')->isoFormat('MMMM YYYY'));
        }

        // "Última actualización": según `users.updated_at` (se actualiza al guardar nombre o contraseña).
        $lastUpdate = '—';
        if ($user->updated_at) {
            if ($user->updated_at->isToday()) {
                $lastUpdate = 'Hoy, '.$user->updated_at->locale('es')->isoFormat('hh:mm a');
            } else {
                $lastUpdate = $user->updated_at->locale('es')->isoFormat('DD [de] MMMM [de] YYYY, hh:mm a');
            }
        }

        // Enlace "Inicio" del breadcrumb: mismo rol efectivo que login/middleware (`roleSlug()`, no solo `role->name`).
        $breadcrumbHome = match ($user->roleSlug()) {
            'admin' => route('admin.dashboard'),
            'coordinator' => route('coordinator.dashboard'),
            'instructor' => route('instructor.dashboard'),
            default => route('dashboard'),
        };

        return view('profile.index', [
            'user' => $user,
            'memberSince' => $memberSince,
            'lastUpdate' => $lastUpdate,
            'breadcrumbs' => [
                'Inicio' => $breadcrumbHome,
                'Mi perfil' => '',
            ],
        ]);
    }

    /**
     * Actualiza solo el nombre del usuario en `users`; el correo no se modifica desde aquí.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Debe ingresar su nombre completo.',
        ]);

        /** @var User $user */
        $user = auth()->user();
        $user->fill(['name' => $validated['name']]);
        $user->save();

        return redirect()
            ->route('profile.index')
            ->with('success', 'Tu nombre se actualizó correctamente.');
    }

    /**
     * Cambia la contraseña en `users` tras validar la actual.
     * `password_confirmation` con regla `same:password` para que el error de coincidencia
     * se muestre bajo el campo de confirmación en la vista.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'password_confirmation' => ['required', 'same:password'],
        ], [
            'current_password.required' => 'Debes ingresar tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.required' => 'Debes ingresar una nueva contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password_confirmation.required' => 'Debes repetir la nueva contraseña.',
            'password_confirmation.same' => 'La confirmación no coincide con la nueva contraseña.',
        ]);

        /** @var User $user */
        $user = auth()->user();
        $user->password = $validated['password'];
        $user->save();

        return redirect()
            ->route('profile.index')
            ->with('success', 'Tu contraseña se actualizó correctamente.');
    }
}
