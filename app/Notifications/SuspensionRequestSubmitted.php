<?php

namespace App\Notifications;

use App\Models\Instructor;
use App\Models\SuspensionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación para el coordinador cuando un instructor envía una
 * nueva solicitud de suspensión. Solo se entrega vía `database`
 * (queda guardada en la tabla `notifications` y aparece en la
 * campanita del coordinador).
 *
 * El payload (data) viaja como JSON e incluye:
 *  - kind:        'suspension_request.submitted' (lo usa la vista y el NotificationController)
 *  - request:     id, tipo + label, motivo (recortado), fecha de solicitud
 *  - instructor:  id, nombre, correo
 *  - assignment:  id y nombre del grupo afectado (puede ser null)
 *  - created_at:  ISO 8601 — fecha que la vista muestra en "hace X tiempo"
 */
class SuspensionRequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public Instructor $instructor,
        public SuspensionRequest $request,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->instructor->loadMissing('user');
        $this->request->loadMissing('assignment.classGroup');

        return [
            'kind' => 'suspension_request.submitted',
            'request' => [
                'id'            => $this->request->id,
                'type'          => $this->request->type,
                'type_label'    => $this->request->typeLabel(),
                'reason_excerpt'=> \Illuminate\Support\Str::limit((string) $this->request->reason, 140),
                'requested_at'  => $this->request->requested_at?->toIso8601String(),
            ],
            'instructor' => [
                'id'    => $this->instructor->id,
                'name'  => $this->instructor->user?->name,
                'email' => $this->instructor->user?->email,
            ],
            'assignment' => [
                'id'         => $this->request->assignment?->id,
                'group_name' => $this->request->assignment?->classGroup?->name,
            ],
            'created_at' => now()->toIso8601String(),
        ];
    }
}
