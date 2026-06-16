<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\InstructorAssignment;
use App\Models\SuspensionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Acciones de la campanita del coordinador:
 *  - markRead: marca una notificación como leída. Según el `kind` del
 *    payload, redirige al panel apropiado para que el coordinador
 *    pueda actuar (revisar autoevaluación, aprobar/rechazar solicitud,
 *    etc.).
 *  - markAllRead: marca todas las notificaciones del coordinador como leídas.
 */
class NotificationController extends Controller
{
    public function markRead(Request $request, string $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notification = $user->notifications()->where('id', $id)->first();
        if ($notification && is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        $data = $notification?->data ?? [];

        // Notificación de autoevaluación enviada por un instructor:
        // si la instructoría sigue existiendo, vamos al panel de
        // evaluaciones del coordinador.
        if (($data['kind'] ?? null) === 'self_evaluation.submitted') {
            $assignmentId = $data['assignment']['id'] ?? null;
            if ($assignmentId && InstructorAssignment::query()->whereKey($assignmentId)->exists()) {
                return redirect()->route('coordinator.evaluations.index');
            }
        }

        // Nueva solicitud de suspensión: llevamos al coordinador al
        // índice de solicitudes para que pueda aprobarla o rechazarla.
        // Si la solicitud ya no existe (por la razón que sea), igual
        // redirigimos al índice para que vea el listado actualizado.
        if (($data['kind'] ?? null) === 'suspension_request.submitted') {
            $requestId = $data['request']['id'] ?? null;
            if ($requestId && SuspensionRequest::query()->whereKey($requestId)->exists()) {
                return redirect()->route('coordinator.suspensions.index');
            }
            return redirect()->route('coordinator.suspensions.index');
        }

        return redirect()->back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->back();
    }
}
