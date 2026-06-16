<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\SuspensionRequest;
use App\Notifications\SuspensionRequestSubmitted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class SuspensionRequestController extends Controller
{
    /**
     * Historial de solicitudes del instructor + formulario para enviar una nueva.
     */
    public function index(Request $request): View
    {
        $instructor = Instructor::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $requests = $instructor->suspensionRequests()
            ->with('assignment.classGroup')
            ->orderByDesc('requested_at')
            ->get();

        $hasPending = $requests->where('status', SuspensionRequest::STATUS_PENDING)->isNotEmpty();

        $activeAssignment = $instructor->instructorAssignments()
            ->with('classGroup')
            ->where('status', 'Activo')
            ->latest()
            ->first();

        return view('instructors.suspensions.index', [
            'requests'         => $requests,
            'hasPending'       => $hasPending,
            'activeAssignment' => $activeAssignment,
            'instructor'       => $instructor,
        ]);
    }

    /**
     * Crea una nueva solicitud de suspensión.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        /** @var Instructor $instructor */
        $instructor = Instructor::query()
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Solo instructores activos pueden solicitar suspensión.
        if (! $instructor->isActive()) {
            return back()->withErrors(['suspension' => 'Tu cuenta ya no está activa; no puedes enviar una solicitud.']);
        }

        // Solo una solicitud pendiente a la vez.
        $hasPending = $instructor->suspensionRequests()
            ->where('status', SuspensionRequest::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            return back()->withErrors(['suspension' => 'Ya tienes una solicitud de suspensión pendiente de revisión.']);
        }

        $validated = $request->validate([
            'type'   => ['required', 'string', 'in:voluntary,force_majeure,other'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ], [
            'type.required'   => 'Debes seleccionar el tipo de solicitud.',
            'type.in'         => 'El tipo de solicitud no es válido.',
            'reason.required' => 'Debes explicar el motivo de la solicitud.',
            'reason.min'      => 'El motivo debe tener al menos 20 caracteres.',
            'reason.max'      => 'El motivo no puede superar 2000 caracteres.',
        ]);

        // Asignación activa (puede ser null si aún no tiene).
        $activeAssignment = $instructor->instructorAssignments()
            ->where('status', 'Activo')
            ->latest()
            ->first();

        $suspensionRequest = SuspensionRequest::create([
            'instructor_id' => $instructor->id,
            'assignment_id' => $activeAssignment?->id,
            'type'          => $validated['type'],
            'reason'        => $validated['reason'],
            'status'        => SuspensionRequest::STATUS_PENDING,
            'requested_at'  => now(),
        ]);

        // Notifica al coordinador propietario del instructor (campanita).
        // Si el instructor no tiene coordinador asignado, no se envía nada
        // y la solicitud queda visible para el admin de cualquier modo.
        $coordinatorUser = $instructor->coordinator?->user;
        if ($coordinatorUser) {
            Notification::send(
                $coordinatorUser,
                new SuspensionRequestSubmitted($instructor, $suspensionRequest),
            );
        }

        return redirect()
            ->route('instructor.suspensions.index')
            ->with('suspension_success', 'Tu solicitud fue enviada. El coordinador la revisará a la brevedad.');
    }
}
