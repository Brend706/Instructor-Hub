<?php

use App\Http\Controllers\Auth\LoginController;
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
        Route::get('/admin/panel', function () {
            return view('dashboard.admin');
        })->name('admin.dashboard');
    });

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
