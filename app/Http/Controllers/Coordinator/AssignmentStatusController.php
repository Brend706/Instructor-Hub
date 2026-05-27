<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Permite al coordinador cambiar el estado de una instructoría
 * (instructor_assignments.status) entre "Activo" y "Finalizado".
 *
 * Finalizar una instructoría es lo que HABILITA las evaluaciones del módulo:
 *   - el instructor podrá completar su autoevaluación
 *   - el coordinador podrá evaluar al instructor
 *   - se podrán importar respuestas de estudiantes y docente
 *
 * Reactivar la regresa a "Activo" (por si se finalizó por error). Las
 * evaluaciones existentes NO se borran al reactivar; solo dejan de
 * aparecer como pendientes.
 */
class AssignmentStatusController extends Controller
{
    public function finalize(Request $request, Instructor $instructor, InstructorAssignment $assignment): RedirectResponse
    {
        $this->ensureBelongsTo($instructor, $assignment);

        $assignment->status = 'Finalizado';
        $assignment->save();

        return back()->with('status', 'Instructoría finalizada. Ya se pueden completar las evaluaciones.');
    }

    public function reactivate(Request $request, Instructor $instructor, InstructorAssignment $assignment): RedirectResponse
    {
        $this->ensureBelongsTo($instructor, $assignment);

        $assignment->status = 'Activo';
        $assignment->save();

        return back()->with('status', 'Instructoría reactivada.');
    }

    /**
     * Valida que el assignment pertenezca al instructor de la URL (evita que
     * un coordinador con dos pestañas abiertas modifique un assignment ajeno
     * pasando un ID al azar).
     */
    private function ensureBelongsTo(Instructor $instructor, InstructorAssignment $assignment): void
    {
        if ((int) $assignment->instructor_id !== (int) $instructor->id) {
            abort(404);
        }
    }
}
