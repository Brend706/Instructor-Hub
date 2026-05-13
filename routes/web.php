<?php

use App\Http\Controllers\Admin\CoordinatorController;
use App\Http\Controllers\Admin\InstructorController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Coordinator\ClassGroupController;
use App\Http\Controllers\ProfileController;
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
                'pctInstructorias' => 12,
                'totalInstructores' => 23,
                'nuevosInstructores' => 3,
                'totalCoordinadores' => 4,
                'asistenciaPromedio' => 87,
                'pctAsistencia' => -2,
                'pctPresencial' => 62,
                'pctEnLinea' => 38,
                'totalPresencial' => 30,
                'totalEnLinea' => 18,
                'semanas' => [6, 9, 7, 12, 10, 8, 11, 13],
                'semanasLabels' => ['S1', 'S2', 'S3', 'S4', 'S5', 'S6', 'S7', 'S8'],
                'instructoresRecientes' => collect([]),
                'coordinadores' => collect([]),
                'actividad' => [],
            ]);
        })->name('admin.dashboard');
    });

    // ruta al crud de coordinadores accesible solo para admins
    Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', fn () => view('admin.dashboard'))->name('dashboard');
        Route::resource('coordinadores', CoordinatorController::class);
        Route::resource('instructores', InstructorController::class)->except(['create', 'show', 'edit']);
    });

    // Panel coordinador: solo usuarios autenticados con rol coordinador (`roleSlug()` / middleware `role:coordinator`).
    // Tras el login, `User::dashboardRouteName()` apunta aquí (`coordinator.dashboard` → vista maquetada).
    // No duplicar estas rutas fuera de `auth`: antes anulaban el middleware y mezclaban vistas.
    Route::middleware(['auth', 'role:coordinator'])->prefix('coordinador')->name('coordinator.')->group(function () {
        Route::get('/dashboard', fn () => view('coordinator.dashboard'))->name('dashboard');

        Route::get('/groups', [ClassGroupController::class, 'index'])->name('groups.index');
        Route::post('/groups', [ClassGroupController::class, 'store'])->name('groups.store');
        Route::put('/groups/{group}', [ClassGroupController::class, 'update'])->name('groups.update');
        Route::delete('/groups/{group}', [ClassGroupController::class, 'destroy'])->name('groups.destroy');
        Route::post('/groups/{group}/assign-instructor', [ClassGroupController::class, 'assignInstructor'])->name('groups.assign-instructor');

        Route::resource('instructores', InstructorController::class)->except(['create', 'show', 'edit']);
    });
    Route::middleware('role:instructor')->group(function () {
        Route::get('/instructor/dashboard', function () {
            return view('dashboard.instructor');
        })->name('instructor.dashboard');
    });

    // Perfil: requiere sesión; cualquier rol autenticado puede ver y editar su propio `users`.
    Route::get('/mi-perfil', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/mi-perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/mi-perfil/contrasena', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

//ruta temporal para el frontend de agregar estudiantes a grupos
Route::get('/coordinador/groups/{group}/students', fn($group) => view('coordinator.groups.students'))
     ->name('coordinator.groups.students');