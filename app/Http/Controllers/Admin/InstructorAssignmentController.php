<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstructorAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InstructorAssignmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $assignments = InstructorAssignment::query()
            ->with(['instructor.user', 'classGroup'])
            ->latest()
            ->paginate(20);

        return view('admin.instructor_assignments.index', [
            'assignments' => $assignments,
        ]);
    }

    /**
     * Display the specified resource (JSON for details modal).
     */
    public function show(InstructorAssignment $assignment): Response
    {
        $assignment->load(['instructor.user', 'classGroup', 'classSessions']);

        return response()->json([
            'id' => $assignment->id,
            'instructor' => $assignment->instructor?->user?->name ?? null,
            'instructor_email' => $assignment->instructor?->user?->email ?? null,
            'class_group' => $assignment->classGroup?->name ?? null,
            'semester' => $assignment->classGroup?->semester ?? null,
            'schedule' => $assignment->schedule,
            'status' => $assignment->status,
            'modality' => $assignment->modality ?? $assignment->classGroup?->modality,
            'classroom' => $assignment->classroom ?? $assignment->classGroup?->classroom,
            'virtual_link' => $assignment->virtual_link,
            'sessions' => $assignment->classSessions->map(function ($s) {
                return [
                    'id' => $s->id,
                    'date' => optional($s->date)->toDateString(),
                    'start_time' => $s->start_time ?? null,
                    'end_time' => $s->end_time ?? null,
                    'attendees_count' => $s->attendances()->count() ?? 0,
                ];
            }),
        ]);
    }
}
