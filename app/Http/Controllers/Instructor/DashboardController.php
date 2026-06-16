<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\SuspensionRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Dashboard del instructor:
 *  - Saludo personalizado (mañana/tarde/noche + nombre real).
 *  - 4 tarjetas con stats reales (estudiantes, sesiones, % asistencia, grupos activos).
 *  - Tarjeta del "grupo activo" (último assignment del instructor con datos reales del grupo).
 *  - Tabla de estudiantes del grupo activo con su % de asistencia.
 *  - Historial de grupos anteriores (resto de assignments del instructor).
 *  - Estado vacío si todavía no tiene grupos asignados.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $instructor = Instructor::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $instructor) {
            // El usuario tiene rol instructor pero no tiene fila en `instructors`.
            // Mostramos la vista en modo "vacío" igual que cuando no hay grupos.
            return view('instructors.dashboard', $this->emptyPayload($request));
        }

        /** @var Collection<int, InstructorAssignment> $assignments */
        $assignments = $instructor->instructorAssignments()
            ->with(['classGroup' => fn ($q) => $q->withCount('students')])
            ->orderByDesc('id')
            ->get();

        if ($assignments->isEmpty()) {
            return view('instructors.dashboard', $this->emptyPayload($request));
        }

        // "Grupo activo" = el assignment con status 'Activo' más reciente, o el más reciente a secas.
        $active = $assignments->firstWhere('status', 'Activo') ?? $assignments->first();

        $assignmentIds = $assignments->pluck('id')->all();

        // Sesiones totales del instructor (todos los grupos juntos).
        $sessions = ClassSession::query()
            ->whereIn('instructor_assignment_id', $assignmentIds)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $sessionsCount = $sessions->count();
        $totalEnrolled = (int) $assignments->sum(fn ($a) => $a->classGroup?->students_count ?? 0);

        // Asistencias totales para %: filas attended=true / (sesiones × estudiantes inscritos).
        $totalAttendances = StudentAttendance::query()
            ->whereIn('session_id', $sessions->pluck('id'))
            ->where('attended', true)
            ->count();

        $attendanceAvg = ($sessionsCount > 0 && $totalEnrolled > 0)
            ? (int) round(min(100, ($totalAttendances / ($sessionsCount * $totalEnrolled)) * 100))
            : 0;

        // ── Datos del grupo activo ──────────────────────────────
        $activeGroup = $active->classGroup;
        $activeSessions = $sessions->where('instructor_assignment_id', $active->id);
        $activeSessionIds = $activeSessions->pluck('id');
        $activeSessionsCount = $activeSessions->count();
        $enrolledActive = (int) ($activeGroup?->students_count ?? 0);

        $lastSession = $activeSessions->sortByDesc(fn ($s) => $s->date?->format('Y-m-d').' '.$s->start_time)->first();
        $lastSessionAttendance = $lastSession
            ? (int) StudentAttendance::query()
                ->where('session_id', $lastSession->id)
                ->where('attended', true)
                ->count()
            : 0;

        // Promedio de asistentes por sesión en el grupo activo.
        $activeTotalAttendances = StudentAttendance::query()
            ->whereIn('session_id', $activeSessionIds)
            ->where('attended', true)
            ->count();
        $activeAvgAttendees = $activeSessionsCount > 0
            ? (int) round($activeTotalAttendances / $activeSessionsCount)
            : 0;

        // ── Estudiantes del grupo activo + % de asistencia individual ──
        /** @var Collection<int, Student> $students */
        $students = $activeGroup
            ? $activeGroup->students()->orderBy('name')->get()
            : new Collection;

        // Map { student_id => # de sesiones donde attended=true } para el grupo activo.
        $attendedPerStudent = StudentAttendance::query()
            ->whereIn('session_id', $activeSessionIds)
            ->where('attended', true)
            ->selectRaw('student_id, COUNT(*) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $studentRows = $students->map(function (Student $student) use ($attendedPerStudent, $activeSessionsCount) {
            $attended = (int) ($attendedPerStudent[$student->id] ?? 0);
            $pct = $activeSessionsCount > 0
                ? (int) round(($attended / $activeSessionsCount) * 100)
                : 0;

            return [
                'name' => $student->name,
                'carnet' => $student->carnet,
                'attendance_pct' => $pct,
                'initials' => $this->initials($student->name),
            ];
        });

        // ── Historial: todos los assignments excepto el activo ──
        $history = $assignments
            ->reject(fn ($a) => $a->id === $active->id)
            ->take(6)
            ->map(fn (InstructorAssignment $a) => [
                'name' => $a->classGroup?->name ?? 'Grupo sin nombre',
                'schedule' => $a->schedule ?? $a->classGroup?->schedule ?? '—',
                'modality' => $a->modality ?? $a->classGroup?->modality ?? '—',
                'semester' => $a->classGroup?->semester ?? '—',
                'students' => (int) ($a->classGroup?->students_count ?? 0),
            ]);

        return view('instructors.dashboard', [
            'greeting' => $this->greeting(),
            'instructorName' => $this->firstName($request->user()->name ?? 'Instructor'),
            'semester' => $activeGroup?->semester ?? '—',
            'hasGroups' => true,

            'stats' => [
                'students' => $enrolledActive,
                'sessions' => $sessionsCount,
                'attendance_avg' => $attendanceAvg,
                'active_groups' => $assignments->count(),
            ],

            'active' => $activeGroup ? [
                'group_name' => $activeGroup->name,
                'professor' => $activeGroup->professor,
                'semester' => $activeGroup->semester,
                'schedule' => $active->schedule ?? $activeGroup->schedule ?? '—',
                'modality' => $active->modality ?? $activeGroup->modality ?? '—',
                'classroom' => $active->classroom ?? $activeGroup->classroom ?? '—',
                'enrolled' => $enrolledActive,
                'avg_attendees' => $activeAvgAttendees,
                'last_session_human' => $lastSession?->date
                    ? Carbon::parse($lastSession->date)->locale('es')->diffForHumans()
                    : null,
                'last_session_count' => $lastSessionAttendance,
            ] : null,

            'studentRows' => $studentRows,
            'history' => $history,

            // Solicitud de suspensión pendiente (si existe), para mostrar aviso en dashboard.
            'pendingSuspension' => $instructor->suspensionRequests()
                ->where('status', SuspensionRequest::STATUS_PENDING)
                ->latest('requested_at')
                ->first(),
        ]);
    }

    /**
     * Payload usado cuando el instructor no tiene grupos asignados.
     */
    private function emptyPayload(Request $request): array
    {
        return [
            'greeting' => $this->greeting(),
            'instructorName' => $this->firstName($request->user()->name ?? 'Instructor'),
            'semester' => '—',
            'hasGroups' => false,
            'stats' => [
                'students' => 0,
                'sessions' => 0,
                'attendance_avg' => 0,
                'active_groups' => 0,
            ],
            'active' => null,
            'studentRows' => collect(),
            'history' => collect(),
            'pendingSuspension' => null,
        ];
    }

    private function greeting(): string
    {
        $hour = (int) now()->format('H');
        if ($hour < 12) {
            return 'Buen día';
        }
        if ($hour < 19) {
            return 'Buenas tardes';
        }

        return 'Buenas noches';
    }

    private function firstName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        return $parts[0] ?? $fullName;
    }

    /**
     * Iniciales para el avatar pequeño (máx. 2 letras).
     */
    private function initials(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return '··';
        }
        $parts = preg_split('/\s+/', $name) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $second = mb_substr($parts[1] ?? '', 0, 1);

        return mb_strtoupper($first.$second) ?: mb_strtoupper(mb_substr($name, 0, 2));
    }
}
