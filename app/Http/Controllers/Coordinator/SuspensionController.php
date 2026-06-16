<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Coordinator;
use App\Models\Instructor;
use App\Models\SuspensionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuspensionController extends Controller
{
    /**
     * Lista de solicitudes de suspensión de los instructores a cargo
     * del coordinador autenticado.
     */
    public function index(): View
    {
        $coordId = $this->currentCoordinatorId();

        // Instructores del coordinador.
        $instructorIds = Instructor::query()
            ->where('coordinator_id', $coordId ?? -1)
            ->pluck('id');

        $requests = SuspensionRequest::query()
            ->whereIn('instructor_id', $instructorIds)
            ->with(['instructor.user', 'assignment.classGroup', 'reviewer'])
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderByDesc('requested_at')
            ->paginate(20);

        $pendingCount = SuspensionRequest::query()
            ->whereIn('instructor_id', $instructorIds)
            ->where('status', SuspensionRequest::STATUS_PENDING)
            ->count();

        return view('coordinator.suspensions.index', compact('requests', 'pendingCount'));
    }

    /**
     * Aprueba la solicitud y cambia el estado del instructor a Suspendido.
     */
    public function approve(Request $request, SuspensionRequest $suspensionRequest): RedirectResponse
    {
        $this->ensureOwns($suspensionRequest);

        if (! $suspensionRequest->isPending()) {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $suspensionRequest->update([
            'status'       => SuspensionRequest::STATUS_APPROVED,
            'reviewed_by'  => auth()->id(),
            'admin_notes'  => $validated['admin_notes'] ?? null,
            'reviewed_at'  => now(),
        ]);

        // Suspender la cuenta del instructor.
        $suspensionRequest->instructor->update(['status' => Instructor::STATUS_SUSPENDED]);

        // Suspender asignación activa si existe.
        if ($suspensionRequest->assignment_id) {
            $suspensionRequest->assignment?->update(['status' => 'Suspendido']);
        }

        $name = $suspensionRequest->instructor->user->name ?? 'el instructor';

        return redirect()
            ->route('coordinator.suspensions.index')
            ->with('success', "Solicitud aprobada. La cuenta de {$name} fue suspendida.");
    }

    /**
     * Rechaza la solicitud (sin cambiar el estado del instructor).
     */
    public function reject(Request $request, SuspensionRequest $suspensionRequest): RedirectResponse
    {
        $this->ensureOwns($suspensionRequest);

        if (! $suspensionRequest->isPending()) {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'admin_notes.required' => 'Debes indicar el motivo del rechazo.',
            'admin_notes.min'      => 'El motivo del rechazo debe tener al menos 5 caracteres.',
        ]);

        $suspensionRequest->update([
            'status'      => SuspensionRequest::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'admin_notes' => $validated['admin_notes'],
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('coordinator.suspensions.index')
            ->with('success', 'Solicitud rechazada y archivada.');
    }

    /**
     * Cambia el estado de un instructor directamente (desde el índice de instructores).
     * Permite: Activo, Suspendido, Bloqueado, Inactivo.
     */
    public function updateInstructorStatus(Request $request, Instructor $instructor): RedirectResponse
    {
        $this->ensureOwnsInstructor($instructor);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:Activo,Inactivo,Suspendido,Bloqueado'],
        ]);

        $instructor->update(['status' => $validated['status']]);

        $name = $instructor->user->name ?? 'El instructor';

        return back()->with('success', "{$name} cambió a estado \"{$validated['status']}\" correctamente.");
    }

    // ── Helpers ────────────────────────────────────────────────

    private function currentCoordinatorId(): ?int
    {
        return Coordinator::query()
            ->where('user_id', auth()->id())
            ->value('id');
    }

    private function ensureOwns(SuspensionRequest $req): void
    {
        $coordId = $this->currentCoordinatorId();
        $ownerCoordId = $req->instructor?->coordinator_id;
        if ((int) $ownerCoordId !== (int) $coordId) {
            abort(404);
        }
    }

    private function ensureOwnsInstructor(Instructor $instructor): void
    {
        $coordId = $this->currentCoordinatorId();
        if ((int) $instructor->coordinator_id !== (int) $coordId) {
            abort(404);
        }
    }
}
