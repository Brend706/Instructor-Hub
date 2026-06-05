<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassGroup;
use App\Models\InstructorAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class InstructorAssignmentController extends Controller
{
    /**
     * Display a listing of all instructor assignments.
     * Supports filtering by semester (?semester=01-2026) and showing all (?all=1).
     */
    public function index(Request $request): View
    {
        $semester = $request->string('semester')->trim()->toString() ?: null;

        $query = InstructorAssignment::query()
            ->with(['instructor.user', 'classGroup'])
            ->latest();

        if ($semester) {
            $query->whereHas('classGroup', fn($q) => $q->where('semester', $semester));
        }

        // Collect available semesters for the filter dropdown.
        $semesters = ClassGroup::query()
            ->whereNotNull('semester')
            ->distinct()
            ->orderByDesc('semester')
            ->pluck('semester');

        if ($request->query('all')) {
            $assignments = $query->get();
            $showAll = true;
        } else {
            $assignments = $query->paginate(20)->withQueryString();
            $showAll = false;
        }

        return view('admin.instructor_assignments.index', [
            'assignments' => $assignments,
            'showAll'     => $showAll,
            'semesters'   => $semesters,
            'semester'    => $semester,
        ]);
    }

    /**
     * Return full assignment details as JSON for the details modal.
     */
    public function show(InstructorAssignment $assignment): JsonResponse
    {
        $assignment->load(['instructor.user', 'classGroup', 'classSessions']);

        return response()->json([
            'id' => $assignment->id,
            'instructor' => [
                'id'    => $assignment->instructor?->id,
                'name'  => $assignment->instructor?->user?->name,
                'email' => $assignment->instructor?->user?->email,
            ],
            'assignment' => [
                'id'           => $assignment->id,
                'schedule'     => $assignment->schedule,
                'status'       => $assignment->status,
                'modality'     => $assignment->modality ?? $assignment->classGroup?->modality,
                'classroom'    => $assignment->classroom ?? $assignment->classGroup?->classroom,
                'virtual_link' => $assignment->virtual_link,
                'created_at'   => optional($assignment->created_at)->toDateTimeString(),
                'updated_at'   => optional($assignment->updated_at)->toDateTimeString(),
            ],
            'class_group' => $assignment->classGroup ? [
                'id'             => $assignment->classGroup->id,
                'name'           => $assignment->classGroup->name,
                'professor'      => $assignment->classGroup->professor ?? null,
                'semester'       => $assignment->classGroup->semester ?? null,
                'modality'       => $assignment->classGroup->modality ?? null,
                'schedule'       => $assignment->classGroup->schedule ?? null,
                'classroom'      => $assignment->classGroup->classroom ?? null,
                'students_count' => $assignment->classGroup->students()->count(),
            ] : null,
            'sessions' => $assignment->classSessions->map(fn($s) => [
                'id'             => $s->id,
                'date'           => optional($s->date)->toDateString(),
                'start_time'     => $s->start_time,
                'end_time'       => $s->end_time,
                'attendees_count'=> $s->attendances()->count(),
                'is_open'        => (bool) $s->is_open,
            ]),
        ]);
    }
}
