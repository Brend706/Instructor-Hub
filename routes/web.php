<?php

use App\Http\Controllers\Admin\CoordinatorController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EvaluationController as AdminEvaluationController;
use App\Http\Controllers\Admin\EvaluationQuestionController as AdminEvaluationQuestionController;
use App\Http\Controllers\Admin\InstructorController;
use App\Http\Controllers\Admin\InstructorAssignmentController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SuspensionController as AdminSuspensionController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Coordinator\ClassGroupController;
use App\Http\Controllers\Coordinator\DashboardController;
use App\Http\Controllers\Coordinator\AssignmentStatusController;
use App\Http\Controllers\Coordinator\EvaluationController as CoordinatorEvaluationController;
use App\Http\Controllers\Coordinator\EvaluationImportController as CoordinatorEvaluationImportController;
use App\Http\Controllers\Coordinator\GroupStudentsController;
use App\Http\Controllers\Coordinator\InstructoriaController;
use App\Http\Controllers\Coordinator\NotificationController as CoordinatorNotificationController;
use App\Http\Controllers\Coordinator\StudentImportController;
use App\Http\Controllers\Coordinator\SuspensionController as CoordinatorSuspensionController;
use App\Http\Controllers\FicabotController;
use App\Http\Controllers\Instructor\AttendanceController as InstructorAttendanceController;
use App\Http\Controllers\Instructor\DashboardController as InstructorDashboardController;
use App\Http\Controllers\Instructor\EvaluationController as InstructorEvaluationController;
use App\Http\Controllers\Instructor\GroupController as InstructorGroupController;
use App\Http\Controllers\Instructor\SessionController;
use App\Http\Controllers\Instructor\SuspensionRequestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuspensionReceiptController;
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

    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Asistencia por QR (público, sin login)
|--------------------------------------------------------------------------
| El estudiante abre el enlace del QR → formulario de carnet → AttendanceController.
| El instructor usa las rutas instructor.session* (autenticado) para abrir/cerrar sesión.
*/
Route::get('/asistencia/{token}', [AttendanceController::class, 'show'])->name('attendance.show');
Route::post('/asistencia/{token}', [AttendanceController::class, 'store'])->name('attendance.store');

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
        Route::get('/instructorias', [InstructorAssignmentController::class, 'index'])->name('instructorias.index');
        Route::get('/instructorias/{assignment}', [InstructorAssignmentController::class, 'show'])->name('instructorias.show');

        // Campanita de notificaciones del admin.
        // POST /admin/notifications/{id}/read  → marca una notificación como leída al hacer clic.
        // POST /admin/notifications/read-all   → marca todas las no leídas como leídas.
        // Ambas viven detrás de role:admin, así que solo administradores pueden invocarlas.
        Route::post('/notifications/{id}/read', [AdminNotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [AdminNotificationController::class, 'markAllRead'])->name('notifications.read-all');
        // GET /admin/notifications/feed → polling AJAX de la campanita (conteo + HTML).
        Route::get('/notifications/feed', [AdminNotificationController::class, 'feed'])->name('notifications.feed');

        // Evaluaciones (admin): vista global de TODAS las evaluaciones del sistema.
        // Index lista instructorías con evaluaciones (filtrable por instructor y ciclo);
        // show muestra el detalle por tipo con todas las respuestas; export descarga
        // el .xlsx consolidado del assignment; por_instructor es un reporte agregado.
        Route::get('/evaluaciones',
            [AdminEvaluationController::class, 'index'])
            ->name('evaluations.index');
        Route::get('/evaluaciones/por-instructor',
            [AdminEvaluationController::class, 'byInstructor'])
            ->name('evaluations.by_instructor');
        Route::get('/evaluaciones/{assignment}',
            [AdminEvaluationController::class, 'show'])
            ->name('evaluations.show');
        Route::get('/evaluaciones/{assignment}/exportar',
            [AdminEvaluationController::class, 'export'])
            ->name('evaluations.export');
        Route::post('/evaluaciones/resultado/{result}/revisar',
            [AdminEvaluationController::class, 'markReviewed'])
            ->name('evaluations.results.review');
        Route::post('/evaluaciones/{assignment}/veredicto',
            [AdminEvaluationController::class, 'saveVerdict'])
            ->name('evaluations.verdict');

        // CRUD de plantillas de preguntas (4 tipos: self/coordinator/student/teacher).
        // Permite agregar, editar, reordenar y desactivar preguntas sin tocar código.
        Route::get('/plantillas-evaluacion/{tipo}',
            [AdminEvaluationQuestionController::class, 'index'])
            ->name('evaluations.questions.index');
        Route::post('/plantillas-evaluacion/{tipo}',
            [AdminEvaluationQuestionController::class, 'store'])
            ->name('evaluations.questions.store');
        Route::put('/plantillas-evaluacion/pregunta/{question}',
            [AdminEvaluationQuestionController::class, 'update'])
            ->name('evaluations.questions.update');
        Route::post('/plantillas-evaluacion/pregunta/{question}/toggle',
            [AdminEvaluationQuestionController::class, 'toggle'])
            ->name('evaluations.questions.toggle');
        Route::post('/plantillas-evaluacion/pregunta/{question}/mover/{direction}',
            [AdminEvaluationQuestionController::class, 'move'])
            ->name('evaluations.questions.move');
        Route::delete('/plantillas-evaluacion/pregunta/{question}',
            [AdminEvaluationQuestionController::class, 'destroy'])
            ->name('evaluations.questions.destroy');

        // Reportes estadísticos del sistema (solo admin).
        Route::get('/reportes/instructores', [AdminReportController::class, 'instructors'])
            ->name('reportes.instructores');
        Route::get('/reportes/coordinaciones', [AdminReportController::class, 'byCoordination'])
            ->name('reportes.coordinaciones');

        // Solicitudes de suspensión (admin ve todas).
        Route::get('/solicitudes', [AdminSuspensionController::class, 'index'])
            ->name('suspensions.index');
        Route::post('/solicitudes/{suspensionRequest}/aprobar', [AdminSuspensionController::class, 'approve'])
            ->name('suspensions.approve');
        Route::post('/solicitudes/{suspensionRequest}/rechazar', [AdminSuspensionController::class, 'reject'])
            ->name('suspensions.reject');
        // Cambio directo de estado desde el índice de instructores (admin puede bloquear).
        Route::post('/instructores/{instructor}/estado', [AdminSuspensionController::class, 'updateInstructorStatus'])
            ->name('instructores.status');
    });

    Route::middleware(['auth', 'role:coordinator'])->prefix('coordinador')->name('coordinator.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/groups', [ClassGroupController::class, 'index'])->name('groups.index');
        Route::post('/groups', [ClassGroupController::class, 'store'])->name('groups.store');
        Route::put('/groups/{group}', [ClassGroupController::class, 'update'])->name('groups.update');
        Route::delete('/groups/{group}', [ClassGroupController::class, 'destroy'])->name('groups.destroy');
        Route::post('/groups/{group}/assign-instructor', [ClassGroupController::class, 'assignInstructor'])->name('groups.assign-instructor');
        Route::delete('/groups/{group}/unassign-instructor', [ClassGroupController::class, 'unassignInstructor'])->name('groups.unassign-instructor');

        Route::get('/groups/{group}/estudiantes', [GroupStudentsController::class, 'index'])->name('groups.enrolled');
        Route::get('/groups/{group}/students', [StudentImportController::class, 'show'])->name('groups.students');
        Route::post('/groups/{group}/students/preview', [StudentImportController::class, 'preview'])->name('groups.students.preview');
        Route::post('/groups/{group}/students/preview-matrix', [StudentImportController::class, 'previewMatrix'])->name('groups.students.preview-matrix');
        Route::post('/groups/{group}/students/import', [StudentImportController::class, 'import'])->name('groups.students.import');
        Route::post('/groups/{group}/students/import-matrix', [StudentImportController::class, 'importMatrix'])->name('groups.students.import-matrix');

        Route::resource('instructores', InstructorController::class)->except(['create', 'show', 'edit']);

        // Instructorías: por instructor, ver todas las sesiones que ha dado (fecha + horas + grupo + asistentes).
        Route::get('/instructorias', [InstructoriaController::class, 'index'])->name('instructorias.index');
        Route::get('/instructorias/{instructor}', [InstructoriaController::class, 'show'])->name('instructorias.show');
        // Descarga .xlsx con todas las sesiones del instructor (botón "Exportar Excel" en la vista show).
        Route::get('/instructorias/{instructor}/export', [InstructoriaController::class, 'export'])->name('instructorias.export');

        // Finalizar / reactivar una instructoría (assignment). Finalizar es lo que
        // habilita las evaluaciones del módulo "Evaluaciones".
        Route::post('/instructorias/{instructor}/asignaciones/{assignment}/finalizar',
            [AssignmentStatusController::class, 'finalize'])
            ->name('instructorias.assignment.finalize');
        Route::post('/instructorias/{instructor}/asignaciones/{assignment}/reactivar',
            [AssignmentStatusController::class, 'reactivate'])
            ->name('instructorias.assignment.reactivate');

        // Evaluaciones del coordinador: listar instructorías finalizadas a su cargo
        // y completar la evaluación al instructor (10 preguntas).
        Route::get('/evaluaciones',
            [CoordinatorEvaluationController::class, 'index'])
            ->name('evaluations.index');
        Route::get('/evaluaciones/{assignment}',
            [CoordinatorEvaluationController::class, 'create'])
            ->name('evaluations.create');
        Route::post('/evaluaciones/{assignment}',
            [CoordinatorEvaluationController::class, 'store'])
            ->name('evaluations.store');

        // Import por Excel: evaluaciones de estudiantes y docente titular.
        // El {tipo} debe ser 'student' o 'teacher' (validado en el controller).
        Route::get('/evaluaciones/{assignment}/importar/{tipo}',
            [CoordinatorEvaluationImportController::class, 'show'])
            ->name('evaluations.import.show');
        Route::get('/evaluaciones/{assignment}/importar/{tipo}/plantilla',
            [CoordinatorEvaluationImportController::class, 'template'])
            ->name('evaluations.import.template');
        Route::post('/evaluaciones/{assignment}/importar/{tipo}',
            [CoordinatorEvaluationImportController::class, 'store'])
            ->name('evaluations.import.store');

        // Campanita de notificaciones (autoevaluaciones de instructores, etc.).
        Route::post('/notificaciones/{id}/leer',
            [CoordinatorNotificationController::class, 'markRead'])
            ->name('notifications.read');
        Route::post('/notificaciones/leer-todas',
            [CoordinatorNotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');
        // GET /coordinator/notificaciones/feed → polling AJAX de la campanita (conteo + HTML).
        Route::get('/notificaciones/feed',
            [CoordinatorNotificationController::class, 'feed'])
            ->name('notifications.feed');

        // Solicitudes de suspensión: listado, aprobación y rechazo.
        Route::get('/solicitudes', [CoordinatorSuspensionController::class, 'index'])
            ->name('suspensions.index');
        Route::post('/solicitudes/{suspensionRequest}/aprobar', [CoordinatorSuspensionController::class, 'approve'])
            ->name('suspensions.approve');
        Route::post('/solicitudes/{suspensionRequest}/rechazar', [CoordinatorSuspensionController::class, 'reject'])
            ->name('suspensions.reject');
        // Cambio directo de estado desde el índice de instructores.
        Route::post('/instructores/{instructor}/estado', [CoordinatorSuspensionController::class, 'updateInstructorStatus'])
            ->name('instructores.status');
    });
    Route::middleware('role:instructor')->group(function () {
        Route::get('/instructor/dashboard', InstructorDashboardController::class)->name('instructor.dashboard');

        Route::get('/instructor/grupos', [InstructorGroupController::class, 'index'])->name('instructor.groups.index');
        Route::get('/instructor/grupos/{assignment}', [InstructorGroupController::class, 'show'])->name('instructor.groups.show');
        Route::put('/instructor/grupos/{assignment}/detalles', [InstructorGroupController::class, 'updateAssignment'])->name('instructor.groups.update');

        // Asistencia: lista de instructorías y, por instructoría, matriz estudiantes × sesiones.
        Route::get('/instructor/asistencia', [InstructorAttendanceController::class, 'index'])->name('instructor.attendance.index');
        Route::get('/instructor/asistencia/{assignment}', [InstructorAttendanceController::class, 'show'])->name('instructor.attendance.show');
        // Descarga .xlsx con la matriz de asistencia (botón "Exportar Excel" en la vista show).
        Route::get('/instructor/asistencia/{assignment}/export', [InstructorAttendanceController::class, 'export'])->name('instructor.attendance.export');

        // Vista "Iniciar sesión": generar QR, código de sesión y finalizar clase.
        Route::get('/instructor/session', [SessionController::class, 'create'])
            ->name('instructor.session');

        Route::post('/instructor/session', [SessionController::class, 'store'])
            ->name('instructor.session.store');

        Route::post('/instructor/session/end', [SessionController::class, 'end'])
            ->name('instructor.session.end');

        Route::get('/instructor/session/attendance-count', [SessionController::class, 'attendanceCount'])
            ->name('instructor.session.attendance-count');

        // Evaluaciones: autoevaluación del instructor (se habilita por
        // instructoría cuando el coordinador la marca como "Finalizada").
        Route::get('/instructor/evaluaciones',
            [InstructorEvaluationController::class, 'index'])
            ->name('instructor.evaluations.index');
        Route::get('/instructor/evaluaciones/{assignment}',
            [InstructorEvaluationController::class, 'create'])
            ->name('instructor.evaluations.create');
        Route::post('/instructor/evaluaciones/{assignment}',
            [InstructorEvaluationController::class, 'store'])
            ->name('instructor.evaluations.store');

        // Solicitudes de suspensión: el instructor ve su historial y envía nuevas.
        Route::get('/instructor/solicitudes', [SuspensionRequestController::class, 'index'])
            ->name('instructor.suspensions.index');
        Route::post('/instructor/solicitud-suspension', [SuspensionRequestController::class, 'store'])
            ->name('instructor.suspension.store');
    });

    // Perfil: requiere sesión; cualquier rol autenticado puede ver y editar su propio `users`.
    Route::get('/mi-perfil', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/mi-perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/mi-perfil/contrasena', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Comprobante PDF de aprobación de suspensión.
    // Único endpoint compartido por admin, coordinador e instructor: el
    // controlador hace el guard por rol y solo entrega el archivo si la
    // solicitud está aprobada y le pertenece al usuario.
    Route::get('/comprobantes/suspension/{suspensionRequest}/pdf', [SuspensionReceiptController::class, 'download'])
        ->name('suspensions.receipt');
});

/*
|--------------------------------------------------------------------------
| FICABOT (asistente rule-based, sin OpenAI)
|--------------------------------------------------------------------------
| Estas rutas viven FUERA del middleware `auth` por dos motivos:
|   1) Si la sesión del usuario expira o el navegador no envía la cookie
|      correctamente, antes el endpoint devolvía 401 y el chat se rompía.
|   2) El bot responde con un banco de respuestas estático (sin acceso a
|      datos sensibles), así que no necesita autenticación para devolver
|      ayuda general.
| El controlador igualmente lee Auth::user() de forma opcional para
| personalizar la respuesta y vincular las solicitudes de soporte cuando
| hay sesión válida.
*/
Route::post('/ficabot/ask', [FicabotController::class, 'ask'])->name('ficabot.ask');
Route::post('/ficabot/support', [FicabotController::class, 'escalate'])->name('ficabot.support');
Route::get('/ficabot/suggestions', [FicabotController::class, 'suggestions'])->name('ficabot.suggestions');
