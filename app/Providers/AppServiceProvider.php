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
        View::composer('layouts.admin', function ($view): void {
            // Rol cargado para etiquetas en sidebar (p. ej. `roleDisplayLabel()`) sin repetir en cada vista.
            auth()->user()?->loadMissing('role');

            $view->with([
                'totalCoordinadores' => Coordinator::query()->count(),
                'totalInstructores' => Instructor::query()->count(),
            ]);
        });
    }
}
