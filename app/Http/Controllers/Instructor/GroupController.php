<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Mis grupos" del instructor: lista de instructorías asignadas,
 * edición de detalles de la asignación y estudiantes inscritos.
 */
class GroupController extends Controller
{
    public function index(Request $request): View
    {
        $instructor = $this->currentInstructor($request);

        $assignments = $instructor->instructorAssignments()
            ->with(['classGroup' => fn ($q) => $q->withCount('students')])
            ->orderByDesc('id')
            ->get();

        return view('instructors.groups.index', [
            'assignments' => $assignments,
        ]);
    }

    /**
     * Detalle: info del grupo + info de la asignación + lista de estudiantes.
     */
    public function show(Request $request, InstructorAssignment $assignment): View
    {
        $this->ensureOwnsAssignment($request, $assignment);

        $group    = $assignment->classGroup;
        $students = $group->students()->orderBy('name')->get();

        return view('instructors.groups.show', [
            'assignment' => $assignment,
            'group'      => $group,
            'students'   => $students,
        ]);
    }

    /**
     * Actualiza los detalles de la instructoría (horario, modalidad, aula/enlace).
     * El coordinador asigna al instructor; el instructor completa estos datos.
     */
    public function updateAssignment(Request $request, InstructorAssignment $assignment): RedirectResponse
    {
        $this->ensureOwnsAssignment($request, $assignment);

        $validated = $request->validate([
            'schedule'     => ['required', 'string', 'max:255'],
            'modality'     => ['required', Rule::in(['Presencial', 'En línea'])],
            'classroom'    => [
                Rule::requiredIf($request->input('modality') === 'Presencial'),
                'nullable', 'string', 'max:255',
            ],
            'virtual_link' => [
                Rule::requiredIf($request->input('modality') === 'En línea'),
                'nullable', 'url', 'max:2048',
            ],
        ], [
            'schedule.required'     => 'Indica el horario de la instructoría.',
            'modality.required'     => 'Selecciona la modalidad.',
            'classroom.required'    => 'Indica el aula física.',
            'virtual_link.required' => 'Indica el enlace virtual.',
            'virtual_link.url'      => 'El enlace debe ser una URL válida.',
        ]);

        $assignment->update([
            'schedule'     => $validated['schedule'],
            'modality'     => $validated['modality'],
            'classroom'    => $validated['modality'] === 'Presencial' ? ($validated['classroom'] ?? null) : null,
            'virtual_link' => $validated['modality'] === 'En línea'   ? ($validated['virtual_link'] ?? null) : null,
        ]);

        return redirect()
            ->route('instructor.groups.show', $assignment)
            ->with('success', 'Datos de la instructoría actualizados correctamente.');
    }

    private function currentInstructor(Request $request): Instructor
    {
        return Instructor::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    private function ensureOwnsAssignment(Request $request, InstructorAssignment $assignment): void
    {
        $instructor = $this->currentInstructor($request);

        if ((int) $assignment->instructor_id !== (int) $instructor->id) {
            throw new ModelNotFoundException;
        }

        $assignment->loadMissing('classGroup');
    }
}
