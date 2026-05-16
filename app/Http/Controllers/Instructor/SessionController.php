<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(Request $request): View
    {
        $assignment = $this->currentAssignmentOrNull($request);

        return view('instructors.session', [
            'assignment' => $assignment,
            'group' => $assignment?->classGroup,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->currentAssignment($request);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'classroom' => ['nullable', 'string', 'max:255'],
            'virtual_link' => ['nullable', 'string', 'max:255'],
        ]);

        $session = ClassSession::create([
            'instructor_assignment_id' => $this->currentAssignment($request)->id,
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['start_time'],
            'classroom' => $validated['classroom'] ?? null,
            'virtual_link' => $validated['virtual_link'] ?? null,
        ]);

        return response()->json([
            'message' => 'Sesión iniciada correctamente.',
            'session_id' => $session->id,
            'started_at' => $session->start_time,
        ], 201);
    }

    public function end(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'integer', 'exists:class_sessions,id'],
        ]);

        $instructor = $this->currentInstructor($request);
        $session = ClassSession::query()
            ->where('id', $validated['session_id'])
            ->whereHas('instructorAssignment', function ($query) use ($instructor) {
                $query->where('instructor_id', $instructor->id);
            })
            ->firstOrFail();

        $session->end_time = now()->format('H:i:s');
        $session->save();

        return response()->json([
            'message' => 'Sesión finalizada correctamente.',
            'ended_at' => $session->end_time,
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

        return $instructor->instructorAssignments()
            ->with('classGroup')
            ->firstOrFail();
    }

    private function currentAssignmentOrNull(Request $request): ?InstructorAssignment
    {
        $instructor = $this->currentInstructor($request);

        return $instructor->instructorAssignments()
            ->with('classGroup')
            ->first();
    }
}
