<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ClassGroup;
use Illuminate\View\View;

/**
 * Lista de estudiantes ya inscritos en un grupo (solo lectura).
 * La importación Excel sigue en StudentImportController.
 */
class GroupStudentsController extends Controller
{
    public function index(ClassGroup $group): View
    {
        $group->load(['instructorAssignments.instructor.user']);
        $assignment = $group->instructorAssignments->first();
        $instructor = $assignment?->instructor;

        $students = $group->students()->orderBy('name')->get();

        return view('coordinator.groups.enrolled', [
            'group' => $group,
            'students' => $students,
            'instructorName' => $instructor?->user?->name,
            'instructorMajor' => $instructor?->major,
        ]);
    }
}
