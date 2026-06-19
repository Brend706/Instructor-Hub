<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Acciones sobre la campanita del admin:
 *  - markRead: marca una notificación como leída (al hacer clic).
 *      Si la notificación es de tipo InstructorCreated, redirige al perfil del instructor.
 *  - markAllRead: marca todas las notificaciones del admin como leídas.
 */
class NotificationController extends Controller
{
    /**
     * Se ejecuta al hacer clic en una notificación del dropdown.
     * Pasos:
     *  1. Toma la notificación del usuario autenticado por su id (UUID en notifications.id).
     *  2. Si aún no estaba leída (read_at = NULL), la marca como leída con markAsRead().
     *  3. Si el payload (data JSON) tiene un instructor existente, redirige a la lista de instructores.
     *  4. En caso contrario, vuelve a la página anterior.
     */
    public function markRead(Request $request, string $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // notifications() es la relación morph que trae Laravel con el trait Notifiable.
        $notification = $user->notifications()->where('id', $id)->first();
        if ($notification && is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        $data = $notification?->data ?? [];

        // Notificación de soporte de FICABOT: no hay vista dedicada todavía,
        // así que simplemente volvemos a la página anterior (el dropdown se
        // refresca al recargar y el badge baja porque ya se marcó como leída).
        if (($data['kind'] ?? null) === 'ficabot.support') {
            return redirect()->back();
        }

        // Notificación de instructor creado: si el instructor sigue existiendo,
        // llevamos al admin directamente a la lista de instructores para que
        // pueda revisarlo / editarlo.
        $instructorId = $data['instructor']['id'] ?? null;
        if ($instructorId && Instructor::query()->whereKey($instructorId)->exists()) {
            return redirect()->route('admin.instructores.index');
        }

        return redirect()->back();
    }

    /**
     * Se ejecuta al pulsar "Marcar todas como leídas".
     * unreadNotifications devuelve la colección de notificaciones sin read_at.
     * markAsRead() actualiza read_at = now() en todas a la vez.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->back();
    }

    /**
     * Endpoint de polling (AJAX). Devuelve el conteo de no leídas y el HTML
     * de la lista ya renderizado con el mismo partial que usa el layout, para
     * que la campanita se actualice sin recargar la página.
     */
    public function feed(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $notifications = $user->notifications()->limit(15)->get();
        $unreadCount = $user->unreadNotifications()->count();

        $html = view('partials.notifications.admin-list', [
            'notifications' => $notifications,
        ])->render();

        return response()->json([
            'count' => $unreadCount,
            'html' => $html,
        ]);
    }
}
