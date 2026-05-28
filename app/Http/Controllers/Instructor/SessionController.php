<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Models\StudentAttendance;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Vista "Iniciar sesión de instructoría" (lado del INSTRUCTOR, requiere login).
 *
 * Maqueta que trabajamos:
 * - Tarjeta del grupo (materia, horario, modalidad, cantidad de estudiantes inscritos).
 * - Fecha y hora de inicio de la sesión.
 * - Botón "Generar QR e iniciar" → crea la sesión en BD y muestra QR + enlace.
 * - Código visible tipo PROGRAMA-2026-004 debajo del QR.
 * - Botón "Finalizar sesión" → cierra el QR; ya no se aceptan más carnets.
 */
class SessionController extends Controller
{
    /**
     * Carga la pantalla con datos del grupo, estadísticas y sesión abierta si existe.
     */
    public function create(Request $request): View
    {
        $assignment = $this->currentAssignmentOrNull($request);
        $group = $assignment?->classGroup;
        $stats = ['sessions_count' => 0, 'attendance_avg' => 0, 'total_attendances' => 0];

        // Si la instructoría está finalizada por el coordinador, el botón
        // "Generar QR" se deshabilita en la vista y `store()` rechaza la
        // petición aunque alguien intente forzarla desde el navegador.
        $assignmentFinalized = $assignment
            && Schema::hasColumn('instructor_assignments', 'status')
            && $assignment->status === 'Finalizado';

        if ($assignment) {
            $sessionIds = ClassSession::query()
                ->where('instructor_assignment_id', $assignment->id)
                ->pluck('id');

            $sessionsCount = $sessionIds->count();
            $totalAttendances = StudentAttendance::query()
                ->whereIn('session_id', $sessionIds)
                ->where('attended', true)
                ->count();

            $enrolled = max(1, (int) $group?->students()->count());
            $avgPct = $sessionsCount > 0
                ? (int) round(min(100, ($totalAttendances / ($sessionsCount * $enrolled)) * 100))
                : 0;

            $stats = [
                'sessions_count' => $sessionsCount,
                'attendance_avg' => $avgPct,
                'total_attendances' => $totalAttendances,
            ];
        }

        // Si el instructor recargó la página con una sesión aún activa, restauramos QR y enlace.
        $openSession = $assignment
            ? ClassSession::query()
                ->where('instructor_assignment_id', $assignment->id)
                ->where('is_open', true)
                ->latest('id')
                ->first()
            : null;

        if ($openSession && empty($openSession->public_token)) {
            $openSession->public_token = Str::random(48);
            $openSession->save();
        }

        $openAttendanceCount = $openSession
            ? $openSession->attendances()->where('attended', true)->count()
            : 0;

        // URL absoluta que va dentro del QR y en el enlace clicable bajo el código.
        $openAttendanceUrl = $openSession?->public_token
            ? url(route('attendance.show', $openSession->public_token, absolute: false))
            : null;

        return view('instructors.session', [
            'assignment' => $assignment,
            'group' => $group,
            'stats' => $stats,
            'openSession' => $openSession,
            'openAttendanceCount' => $openAttendanceCount,
            'openAttendanceUrl' => $openAttendanceUrl,
            'assignmentFinalized' => $assignmentFinalized,
        ]);
    }

    /**
     * AJAX al pulsar "Generar QR e iniciar": crea fila en `class_sessions` y devuelve URL del QR.
     */
    public function store(Request $request): JsonResponse
    {
        $assignment = $this->currentAssignment($request);

        // Doble candado: el coordinador puede haber finalizado la
        // instructoría mientras el instructor tenía la pantalla abierta.
        // En ese caso no se permite generar QR aunque pulse el botón.
        if (Schema::hasColumn('instructor_assignments', 'status')
            && $assignment->status === 'Finalizado'
        ) {
            return response()->json([
                'message' => 'Esta instructoría fue finalizada por tu coordinador. No puedes iniciar nuevas sesiones.',
            ], 422);
        }

        $existing = ClassSession::query()
            ->where('instructor_assignment_id', $assignment->id)
            ->where('is_open', true)
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'Ya tienes una sesión activa. Finalízala antes de iniciar otra.',
            ], 422);
        }

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
        ]);

        // Token secreto en la URL del QR; session_code es el texto legible bajo el QR (ej. PROGRAMA-2026-004).
        $token = Str::random(48);
        $session = ClassSession::query()->create([
            'instructor_assignment_id' => $assignment->id,
            'public_token' => $token,
            'session_code' => 'TMP',
            'is_open' => true,
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['start_time'],
        ]);

        $prefix = strtoupper(Str::slug(Str::limit($assignment->classGroup->name ?? 'GRP', 8, '')));
        $session->session_code = $prefix.'-'.now()->format('Y').'-'.str_pad((string) $session->id, 3, '0', STR_PAD_LEFT);
        $session->save();

        return response()->json([
            'message' => 'Sesión iniciada correctamente.',
            'session_id' => $session->id,
            'session_code' => $session->session_code,
            'attendance_url' => url(route('attendance.show', $session->public_token, absolute: false)),
            'started_at' => $session->start_time,
        ], 201);
    }

    /**
     * AJAX al pulsar "Finalizar sesión": is_open = false; el enlace/QR deja de aceptar carnets.
     */
    public function end(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'integer', 'exists:class_sessions,id'],
        ]);

        $instructor = $this->currentInstructor($request);
        $session = ClassSession::query()
            ->where('id', $validated['session_id'])
            ->where('is_open', true)
            ->whereHas('instructorAssignment', function ($query) use ($instructor) {
                $query->where('instructor_id', $instructor->id);
            })
            ->firstOrFail();

        $session->end_time = now()->format('H:i:s');
        $session->is_open = false;
        $session->save();

        return response()->json([
            'message' => 'Sesión finalizada correctamente.',
            'ended_at' => $session->end_time,
            'attendance_count' => $session->attendances()->where('attended', true)->count(),
        ]);
    }

    private function currentInstructor(Request $request): Instructor
    {
        return Instructor::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    private function currentAssignment(Request $request): InstructorAssignment
    {
        $instructor = $this->currentInstructor($request);

        $query = $instructor->instructorAssignments()->with('classGroup');
        if (Schema::hasColumn('instructor_assignments', 'status')) {
            $active = (clone $query)->where('status', 'Activo')->first();
            if ($active) {
                return $active;
            }
        }

        return $query->firstOrFail();
    }

    private function currentAssignmentOrNull(Request $request): ?InstructorAssignment
    {
        try {
            return $this->currentAssignment($request);
        } catch (ModelNotFoundException) {
            return null;
        }
    }
}
