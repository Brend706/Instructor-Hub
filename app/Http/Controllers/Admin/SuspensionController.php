<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\SuspensionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuspensionController extends Controller
{
    /**
     * Lista TODAS las solicitudes del sistema (el admin las ve todas).
     */
    public function index(): View
    {
        $requests = SuspensionRequest::query()
            ->with(['instructor.user', 'assignment.classGroup', 'reviewer'])
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderByDesc('requested_at')
            ->paginate(20);

        $pendingCount = SuspensionRequest::query()
            ->where('status', SuspensionRequest::STATUS_PENDING)
            ->count();

        return view('admin.suspensions.index', compact('requests', 'pendingCount'));
    }

    /**
     * Aprueba la solicitud y cambia el estado del instructor a Suspendido.
     */
    public function approve(Request $request, SuspensionRequest $suspensionRequest): RedirectResponse
    {
        if (! $suspensionRequest->isPending()) {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $suspensionRequest->update([
            'status'      => SuspensionRequest::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'admin_notes' => $validated['admin_notes'] ?? null,
            'reviewed_at' => now(),
        ]);

        $suspensionRequest->instructor->update(['status' => Instructor::STATUS_SUSPENDED]);

        if ($suspensionRequest->assignment_id) {
            $suspensionRequest->assignment?->update(['status' => 'Suspendido']);
        }

        $name = $suspensionRequest->instructor->user->name ?? 'el instructor';

        return redirect()
            ->route('admin.suspensions.index')
            ->with('success', "Solicitud aprobada. La cuenta de {$name} fue suspendida.");
    }

    /**
     * Rechaza la solicitud.
     */
    public function reject(Request $request, SuspensionRequest $suspensionRequest): RedirectResponse
    {
        if (! $suspensionRequest->isPending()) {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'admin_notes.required' => 'Debes indicar el motivo del rechazo.',
        ]);

        $suspensionRequest->update([
            'status'      => SuspensionRequest::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'admin_notes' => $validated['admin_notes'],
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('admin.suspensions.index')
            ->with('success', 'Solicitud rechazada y archivada.');
    }

    /**
     * Cambio directo de estado de cualquier instructor (solo admin).
     * Permite también reactivar (status = Activo).
     */
    public function updateInstructorStatus(Request $request, Instructor $instructor): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:Activo,Inactivo,Suspendido,Bloqueado'],
        ]);

        $instructor->update(['status' => $validated['status']]);

        $name = $instructor->user->name ?? 'El instructor';

        return back()->with('success', "{$name} cambió a estado \"{$validated['status']}\" correctamente.");
    }
}
