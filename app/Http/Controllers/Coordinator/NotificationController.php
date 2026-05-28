<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\InstructorAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Acciones de la campanita del coordinador:
 *  - markRead: marca una notificación como leída. Si el payload identifica
 *    una autoevaluación (`kind = self_evaluation.submitted`), redirige al
 *    índice de evaluaciones del coordinador para revisarla.
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

        return redirect()->back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->back();
    }
}
