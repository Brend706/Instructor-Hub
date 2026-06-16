<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coordinator;
use App\Models\Instructor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $now       = Carbon::now();
        $thisMonth = $now->copy()->startOfMonth();
        $lastMonth = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // ── 1. Sesiones este mes vs mes anterior ─────────────────
        $sesionesEsteMes  = DB::table('class_sessions')
            ->whereYear('date', $now->year)
            ->whereMonth('date', $now->month)
            ->count();

        $sesionesUltimoMes = DB::table('class_sessions')
            ->whereYear('date', $lastMonth->year)
            ->whereMonth('date', $lastMonth->month)
            ->count();

        $pctSesiones = $sesionesUltimoMes > 0
            ? (int) round((($sesionesEsteMes - $sesionesUltimoMes) / $sesionesUltimoMes) * 100)
            : 0;

        // ── 2. Instructores activos y nuevos este mes ─────────────
        $totalInstructores  = Instructor::where('status', 'Activo')->count();
        $nuevosInstructores = Instructor::where('created_at', '>=', $thisMonth)->count();

        // ── 3. Coordinadores ─────────────────────────────────────
        $totalCoordinadores = Coordinator::count();

        // ── 4. Asistencia promedio general (% de estudiantes que asistieron) ──
        $asistenciaRow = DB::table('student_attendances')
            ->selectRaw('ROUND(AVG(CASE WHEN attended = 1 THEN 100.0 ELSE 0 END), 1) as rate')
            ->first();
        $asistenciaPromedio = (float) ($asistenciaRow->rate ?? 0);

        // Asistencia mes anterior para comparar
        $asistenciaMesAnteriorRow = DB::table('student_attendances')
            ->join('class_sessions', 'student_attendances.session_id', '=', 'class_sessions.id')
            ->whereYear('class_sessions.date', $lastMonth->year)
            ->whereMonth('class_sessions.date', $lastMonth->month)
            ->selectRaw('ROUND(AVG(CASE WHEN student_attendances.attended = 1 THEN 100.0 ELSE 0 END), 1) as rate')
            ->first();
        $asistanciaMesAnterior = (float) ($asistenciaMesAnteriorRow->rate ?? 0);
        $pctAsistencia = (int) round($asistenciaPromedio - $asistanciaMesAnterior);

        // ── 5. Gráfica de barras: sesiones por semana del mes actual ──
        $semanas      = [];
        $semanasLabels = [];
        $startOfMonth  = $now->copy()->startOfMonth();
        $endOfMonth    = $now->copy()->endOfMonth();

        // Agrupar sesiones por número de semana ISO dentro del mes
        $sesionesPorSemana = DB::table('class_sessions')
            ->whereYear('date', $now->year)
            ->whereMonth('date', $now->month)
            ->selectRaw('WEEK(date, 3) as semana_iso, COUNT(*) as total')
            ->groupBy('semana_iso')
            ->orderBy('semana_iso')
            ->get();

        if ($sesionesPorSemana->isEmpty()) {
            // Sin datos: mostrar semanas vacías del mes
            $weekNum = 1;
            for ($d = $startOfMonth->copy(); $d->lte($endOfMonth); $d->addWeek()) {
                $semanas[]       = 0;
                $semanasLabels[] = 'S' . $weekNum++;
            }
        } else {
            foreach ($sesionesPorSemana as $idx => $row) {
                $semanas[]       = (int) $row->total;
                $semanasLabels[] = 'S' . ($idx + 1);
            }
        }

        // ── 6. Modalidad de las instructorías activas ─────────────
        $modalidades = DB::table('instructor_assignments')
            ->where('status', 'Activo')
            ->whereNotNull('modality')
            ->selectRaw("
                SUM(CASE WHEN LOWER(modality) IN ('presencial','presencal') THEN 1 ELSE 0 END) as presencial,
                SUM(CASE WHEN LOWER(modality) NOT IN ('presencial','presencal') AND modality IS NOT NULL THEN 1 ELSE 0 END) as virtual,
                COUNT(*) as total
            ")
            ->first();

        $totalAsignaciones = (int) ($modalidades->total ?? 0);
        $totalPresencial   = (int) ($modalidades->presencial ?? 0);
        $totalEnLinea      = (int) ($modalidades->virtual ?? 0);

        $pctPresencial = $totalAsignaciones > 0
            ? (int) round($totalPresencial / $totalAsignaciones * 100) : 0;
        $pctEnLinea = $totalAsignaciones > 0
            ? (int) round($totalEnLinea / $totalAsignaciones * 100) : 0;

        // ── 7. Actividad reciente (mezcla de eventos recientes) ───
        $actividad = [];

        // Instructores registrados recientemente
        $nuevos = DB::table('instructors')
            ->join('users', 'instructors.user_id', '=', 'users.id')
            ->select('users.name', 'instructors.created_at')
            ->orderByDesc('instructors.created_at')
            ->limit(3)
            ->get();
        foreach ($nuevos as $n) {
            $actividad[] = [
                'icon'     => 'user-plus',
                'bg'       => '#EEF2FF',
                'color'    => '#7F77DD',
                'usuario'  => $n->name,
                'accion'   => 'fue registrado como instructor',
                'tiempo'   => Carbon::parse($n->created_at)->diffForHumans(),
                'contexto' => 'Sistema',
                'ts'       => $n->created_at,
            ];
        }

        // Evaluaciones enviadas recientemente
        $evals = DB::table('evaluation_results')
            ->join('instructors', 'evaluation_results.instructor_id', '=', 'instructors.id')
            ->join('users', 'instructors.user_id', '=', 'users.id')
            ->join('evaluation_types', 'evaluation_results.evaluation_type_id', '=', 'evaluation_types.id')
            ->whereNotNull('evaluation_results.submitted_at')
            ->select('users.name', 'evaluation_types.name as tipo', 'evaluation_results.submitted_at')
            ->orderByDesc('evaluation_results.submitted_at')
            ->limit(3)
            ->get();
        foreach ($evals as $e) {
            $actividad[] = [
                'icon'     => 'clipboard-check',
                'bg'       => '#F0FDF4',
                'color'    => '#16A34A',
                'usuario'  => $e->name,
                'accion'   => 'completó evaluación: ' . $e->tipo,
                'tiempo'   => Carbon::parse($e->submitted_at)->diffForHumans(),
                'contexto' => 'Evaluaciones',
                'ts'       => $e->submitted_at,
            ];
        }

        // Solicitudes de suspensión recientes
        $suspensions = DB::table('suspension_requests')
            ->join('instructors', 'suspension_requests.instructor_id', '=', 'instructors.id')
            ->join('users', 'instructors.user_id', '=', 'users.id')
            ->select('users.name', 'suspension_requests.status', 'suspension_requests.requested_at')
            ->orderByDesc('suspension_requests.requested_at')
            ->limit(2)
            ->get();
        foreach ($suspensions as $s) {
            $actividad[] = [
                'icon'     => 'alert-circle',
                'bg'       => '#FFFBEB',
                'color'    => '#D97706',
                'usuario'  => $s->name,
                'accion'   => 'solicitó suspensión de instructoría',
                'tiempo'   => Carbon::parse($s->requested_at)->diffForHumans(),
                'contexto' => 'Solicitudes',
                'ts'       => $s->requested_at,
            ];
        }

        // Ordenar por timestamp descendente y tomar los 6 más recientes
        usort($actividad, fn($a, $b) => strcmp($b['ts'], $a['ts']));
        $actividad = array_slice($actividad, 0, 6);

        // ── 8. Instructores registrados recientemente ─────────────
        $instructoresRecientes = DB::table('instructors')
            ->join('users', 'instructors.user_id', '=', 'users.id')
            ->leftJoin('coordinators', 'instructors.coordinator_id', '=', 'coordinators.id')
            ->leftJoin('users as cu', 'coordinators.user_id', '=', 'cu.id')
            ->select(
                'instructors.id',
                'users.name',
                'instructors.major',
                'instructors.status',
                'instructors.category',
                DB::raw('COALESCE(coordinators.school_name, coordinators.catedra, cu.name) as coord_label')
            )
            ->orderByDesc('instructors.created_at')
            ->limit(6)
            ->get();

        // ── 9. Coordinadores con métricas de actividad ────────────
        $coordinadores = DB::table('coordinators')
            ->join('users', 'coordinators.user_id', '=', 'users.id')
            ->leftJoin('instructors', 'instructors.coordinator_id', '=', 'coordinators.id')
            ->select(
                'coordinators.id',
                'users.name',
                DB::raw('COALESCE(coordinators.school_name, coordinators.catedra) as area'),
                DB::raw('COUNT(DISTINCT instructors.id) as instructores_count')
            )
            ->groupBy('coordinators.id', 'users.name', 'coordinators.school_name', 'coordinators.catedra')
            ->orderBy('users.name')
            ->get();

        // Sesiones este mes por coordinador
        $sesionesPorCoord = DB::table('class_sessions')
            ->join('instructor_assignments', 'class_sessions.instructor_assignment_id', '=', 'instructor_assignments.id')
            ->join('instructors', 'instructor_assignments.instructor_id', '=', 'instructors.id')
            ->whereYear('class_sessions.date', $now->year)
            ->whereMonth('class_sessions.date', $now->month)
            ->select('instructors.coordinator_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('instructors.coordinator_id')
            ->pluck('cnt', 'coordinator_id');

        // Asistencia por coordinador
        $asistenciaPorCoord = DB::table('student_attendances')
            ->join('class_sessions', 'student_attendances.session_id', '=', 'class_sessions.id')
            ->join('instructor_assignments', 'class_sessions.instructor_assignment_id', '=', 'instructor_assignments.id')
            ->join('instructors', 'instructor_assignments.instructor_id', '=', 'instructors.id')
            ->select(
                'instructors.coordinator_id',
                DB::raw('ROUND(AVG(CASE WHEN student_attendances.attended = 1 THEN 100.0 ELSE 0 END), 1) as rate')
            )
            ->groupBy('instructors.coordinator_id')
            ->pluck('rate', 'coordinator_id');

        // Inyectar sesiones y asistencia en cada coordinador
        $coordinadores = $coordinadores->map(function ($c) use ($sesionesPorCoord, $asistenciaPorCoord) {
            $c->sesiones_count = $sesionesPorCoord[$c->id] ?? 0;
            $c->asistencia     = (float) ($asistenciaPorCoord[$c->id] ?? 0);
            return $c;
        });

        // ── Solicitudes de suspensión pendientes (para badge sidebar) ──
        $pendingSuspensiones = DB::table('suspension_requests')
            ->where('status', 'pending')
            ->count();

        return view('admin.dashboard', compact(
            'sesionesEsteMes',
            'pctSesiones',
            'totalInstructores',
            'nuevosInstructores',
            'totalCoordinadores',
            'asistenciaPromedio',
            'pctAsistencia',
            'pctPresencial',
            'pctEnLinea',
            'totalPresencial',
            'totalEnLinea',
            'semanas',
            'semanasLabels',
            'instructoresRecientes',
            'coordinadores',
            'actividad',
            'pendingSuspensiones'
        ));
    }
}
