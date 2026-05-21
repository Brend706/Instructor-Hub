<?php

namespace App\Providers;

use App\Models\Coordinator;
use App\Models\Instructor;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // View::composer se ejecuta cada vez que se va a renderizar 'layouts.admin'.
        // Aquí inyectamos variables comunes para que la vista no tenga que pedirlas a cada controlador.
        View::composer('layouts.admin', function ($view): void {
            // Rol cargado para etiquetas en sidebar (p. ej. `roleDisplayLabel()`) sin repetir en cada vista.
            $user = auth()->user();
            $user?->loadMissing('role');

            // Notificaciones de la campanita del admin (creación de instructores, etc.).
            // Por defecto, colección vacía y contador 0 (para roles que no son admin).
            $notifications = collect();
            $notifCount = 0;
            // Solo los admins ven notificaciones en la campanita.
            if ($user && $user->roleSlug() === 'admin') {
                // notifications(): todas las notificaciones (leídas y no leídas), las más recientes primero.
                // Limit 15 para no inflar el dropdown.
                $notifications = $user->notifications()->limit(15)->get();
                // unreadNotifications(): solo las que tienen read_at = NULL. Sirve para el badge rojo.
                $notifCount = $user->unreadNotifications()->count();
            }

            // with(): pasa las variables al Blade de layouts.admin.
            $view->with([
                'totalCoordinadores' => Coordinator::query()->count(),
                'totalInstructores' => Instructor::query()->count(),
                'notifications' => $notifications,
                'notifCount' => $notifCount,
            ]);
        });
    }
}
