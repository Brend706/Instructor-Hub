<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\CoordinatorController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        /** @var User $user */
        $user = Auth::user();
        $user->loadMissing('role');
        $route = $user->dashboardRouteName();
        if ($route === 'login') {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta no tiene un rol válido asignado.',
            ]);
        }

        return redirect()->route($route);
    }

    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', function () {
        /** @var User $user */
        $user = Auth::user();
        $user->loadMissing('role');
        $route = $user->dashboardRouteName();
        if ($route === 'login') {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta no tiene un rol válido asignado.',
            ]);
        }

        return redirect()->route($route);
    })->name('dashboard');

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', function () {
            // Datos de ejemplo para el dashboard que despues seran enviados desde el backend
            return view('admin.dashboard', [
                'totalInstructoriasmes' => 48,
                'pctInstructorias'      => 12,
                'totalInstructores'     => 23,
                'nuevosInstructores'    => 3,
                'totalCoordinadores'    => 4,
                'asistenciaPromedio'    => 87,
                'pctAsistencia'         => -2,
                'pctPresencial'         => 62,
                'pctEnLinea'            => 38,
                'totalPresencial'       => 30,
                'totalEnLinea'          => 18,
                'semanas'               => [6,9,7,12,10,8,11,13],
                'semanasLabels'         => ['S1','S2','S3','S4','S5','S6','S7','S8'],
                'instructoresRecientes' => collect([]),
                'coordinadores'         => collect([]),
                'actividad'             => [],
            ]);
        })->name('admin.dashboard');
    });

    //ruta al crud de coordinadores accesible solo para admins
    Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', fn() => view('admin.dashboard'))->name('dashboard');
        Route::resource('coordinadores', CoordinatorController::class);
    });

    //ruta al crud de instructores accesible solo para admins


    //proximamente redirigiran a los dashboard reales de cada rol
    Route::middleware('role:coordinator')->group(function () {
        Route::get('/coordinator/panel', function () {
            return view('dashboard.coordinator');
        })->name('coordinator.dashboard');
    });
    Route::middleware('role:instructor')->group(function () {
        Route::get('/instructor/panel', function () {
            return view('dashboard.instructor');
        })->name('instructor.dashboard');
    });
});

//ruta temporal al perfil de usuario que ha iniciado sesion
Route::get('/mi-perfil', function () {
    return view('profile.index');
})->name('profile.index');

// (Se removieron rutas temporales duplicadas fuera de auth)
//ruta directa al crud de instructores sin acceso reestingido, ruta temporal
Route::get('/admin/instructores', fn() => view('admin.instructors.index'))->name('admin.instructors.index');

