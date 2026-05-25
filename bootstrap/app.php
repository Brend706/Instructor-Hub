<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // FICABOT: las llamadas AJAX desde el widget no envían el token CSRF
        // por header siempre (el meta tag puede quedar viejo si la sesión se
        // regenera). Como el bot es rule-based y solo crea notificaciones para
        // el admin (con datos de contacto validados), sacarlas de CSRF no
        // expone información sensible.
        $middleware->validateCsrfTokens(except: [
            'ficabot/ask',
            'ficabot/support',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
