<?php

use App\Http\Controllers\Admin\CoordinatorController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InstructorController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Coordinator\ClassGroupController;
use App\Http\Controllers\Coordinator\DashboardController;
use App\Http\Controllers\Coordinator\StudentImportController;
use App\Http\Controllers\Instructor\SessionController;
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

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('coordinadores', CoordinatorController::class)->except(['create', 'show', 'edit']);
        Route::resource('instructores', InstructorController::class)->except(['create', 'show', 'edit']);
    });

    Route::middleware(['auth', 'role:coordinator'])->prefix('coordinador')->name('coordinator.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/groups', [ClassGroupController::class, 'index'])->name('groups.index');
        Route::post('/groups', [ClassGroupController::class, 'store'])->name('groups.store');
        Route::put('/groups/{group}', [ClassGroupController::class, 'update'])->name('groups.update');
        Route::delete('/groups/{group}', [ClassGroupController::class, 'destroy'])->name('groups.destroy');
        Route::post('/groups/{group}/assign-instructor', [ClassGroupController::class, 'assignInstructor'])->name('groups.assign-instructor');

        Route::get('/groups/{group}/students', [StudentImportController::class, 'show'])->name('groups.students');
        Route::post('/groups/{group}/students/preview', [StudentImportController::class, 'preview'])->name('groups.students.preview');
        Route::post('/groups/{group}/students/preview-matrix', [StudentImportController::class, 'previewMatrix'])->name('groups.students.preview-matrix');
        Route::post('/groups/{group}/students/import', [StudentImportController::class, 'import'])->name('groups.students.import');
        Route::post('/groups/{group}/students/import-matrix', [StudentImportController::class, 'importMatrix'])->name('groups.students.import-matrix');

        Route::resource('instructores', InstructorController::class)->except(['create', 'show', 'edit']);
    });
    Route::middleware('role:instructor')->group(function () {
        Route::get('/instructor/dashboard', function () {
            return view('instructors.dashboard');
        })->name('instructor.dashboard');

        Route::get('/instructor/session', [SessionController::class, 'create'])
            ->name('instructor.session');

        Route::post('/instructor/session', [SessionController::class, 'store'])
            ->name('instructor.session.store');

        Route::post('/instructor/session/end', [SessionController::class, 'end'])
            ->name('instructor.session.end');
    });

    // Perfil: requiere sesión; cualquier rol autenticado puede ver y editar su propio `users`.
    Route::get('/mi-perfil', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/mi-perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/mi-perfil/contrasena', [ProfileController::class, 'updatePassword'])->name('profile.password');
});
