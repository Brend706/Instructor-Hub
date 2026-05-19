<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Mis grupos" del instructor: lista de instructorías asignadas y estudiantes inscritos.
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
     * Lista de estudiantes del grupo (carnet, nombre, correo).
     */
    public function show(Request $request, InstructorAssignment $assignment): View
    {
        $this->ensureOwnsAssignment($request, $assignment);

        $group = $assignment->classGroup;
        $students = $group->students()->orderBy('name')->get();

        return view('instructors.groups.show', [
            'assignment' => $assignment,
            'group' => $group,
            'students' => $students,
        ]);
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
