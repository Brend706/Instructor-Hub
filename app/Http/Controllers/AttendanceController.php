<?php

namespace App\Http\Controllers;

use App\Models\ClassSession;
use App\Models\Student;
use App\Models\StudentAttendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Asistencia por QR (lado del ESTUDIANTE, sin login).
 *
 * Flujo que pediste en las maquetas:
 * 1) El instructor genera un QR con un enlace único de sesión.
 * 2) El estudiante escanea el QR (o abre el enlace debajo del código).
 * 3) Ve un formulario para escribir su número de carnet.
 * 4) Si el carnet existe en `students` del mismo grupo de la clase → se guarda UNA asistencia en `student_attendances`.
 * 5) Si ya había marcado en esa sesión → solo mensaje "Ya se registró tu asistencia" (sin volver a pedir carnet).
 * 6) Si el carnet no está inscrito en el grupo → error y puede corregir el número.
 */
class AttendanceController extends Controller
{
    /**
     * Pantalla pública al abrir el enlace del QR: formulario de carnet.
     * URL ejemplo: /asistencia/{public_token}
     */
    public function show(string $token): View|RedirectResponse
    {
        // Solo sesiones con is_open = true (el instructor no las ha finalizado).
        $session = $this->openSessionOrNull($token);

        if ($session === null) {
            return view('attendance.closed', [
                'message' => 'Esta sesión no está disponible o ya fue finalizada.',
            ]);
        }

        $group = $session->instructorAssignment->classGroup;

        return view('attendance.checkin', [
            'session' => $session,
            'groupName' => $group->name,
            'token' => $token,
        ]);
    }

    /**
     * El estudiante envía su carnet (POST del formulario).
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $session = $this->openSessionOrNull($token);

        if ($session === null) {
            return redirect()
                ->route('attendance.show', $token)
                ->withErrors(['carnet' => 'La sesión ya no acepta registros.']);
        }

        $validated = $request->validate([
            'carnet' => ['required', 'string', 'max:64'],
        ], [
            'carnet.required' => 'Debes ingresar tu número de carnet.',
        ]);

        $carnet = trim($validated['carnet']);
        // Grupo de la instructoría ligada a esta sesión (misma materia que importó el coordinador en Excel).
        $classGroupId = $session->instructorAssignment->class_group_id;

        $student = Student::query()
            ->where('class_group_id', $classGroupId)
            ->where('carnet', $carnet)
            ->first();

        // Carnet no encontrado en la lista del grupo → no se registra asistencia.
        if ($student === null) {
            return redirect()
                ->route('attendance.show', $token)
                ->withInput()
                ->withErrors([
                    'carnet' => 'No estás inscrito en esta clase con ese carnet. Verifica el número o contacta a tu coordinador.',
                ]);
        }

        // Una sola asistencia por estudiante por sesión (mismo carnet no puede marcar dos veces).
        $already = StudentAttendance::query()
            ->where('session_id', $session->id)
            ->where('student_id', $student->id)
            ->exists();

        if ($already) {
            return redirect()
                ->route('attendance.show', $token)
                ->with('registered', true)
                ->with('studentName', $student->name)
                ->with('alreadyRegistered', true);
        }

        StudentAttendance::query()->create([
            'session_id' => $session->id,
            'student_id' => $student->id,
            'attended' => true,
        ]);

        return redirect()
            ->route('attendance.show', $token)
            ->with('registered', true)
            ->with('studentName', $student->name);
    }

    /**
     * Sesión válida para recibir asistencias: token correcto y clase aún no finalizada.
     */
    private function openSessionOrNull(string $token): ?ClassSession
    {
        return ClassSession::query()
            ->where('public_token', $token)
            ->where('is_open', true)
            ->with(['instructorAssignment.classGroup'])
            ->first();
    }
}
