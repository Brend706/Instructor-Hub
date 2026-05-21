<?php

namespace App\Notifications;

use App\Models\Instructor;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación para administradores cuando un coordinador crea un instructor.
 * Se entrega solo por el canal `database` (se guarda en la tabla `notifications`).
 *
 * El contenido (data) viaja como JSON e incluye:
 *  - instructor: id, nombre, correo, carrera
 *  - creator:    id, nombre, rol del creador (coordinador que lo dio de alta)
 *  - created_at: fecha/hora de creación (ISO 8601)
 */
class InstructorCreated extends Notification
{
    // Permite que la notificación pueda encolarse (no se usa aquí, pero es estándar de Laravel).
    use Queueable;

    /**
     * Recibe los datos mínimos para describir el evento:
     *  - $instructor: el modelo recién creado (con la relación user cargada para nombre/correo).
     *  - $creator:    el usuario logueado que disparó la creación (en este flujo, un coordinador).
     * Propiedades `public` para que Laravel pueda serializarlas si se encola la notificación.
     */
    public function __construct(
        public Instructor $instructor,
        public User $creator,
    ) {}

    /**
     * via() le dice a Laravel por qué canales se entrega la notificación.
     * `database` = guarda una fila en la tabla `notifications` (no envía correo, ni broadcast).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * toArray() construye el JSON que se guarda en `notifications.data`.
     * Esa columna luego se lee desde la vista del dropdown del admin para mostrar el mensaje.
     *
     * Se incluyen tres bloques:
     *  - instructor: a quién se creó (para el título de la notificación).
     *  - creator:    quién lo creó (nombre del coordinador y su rol).
     *  - created_at: marca de tiempo en formato ISO 8601, fácil de re-parsear con Carbon.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // Aseguramos que la relación user esté cargada para acceder a nombre/correo.
        $this->instructor->loadMissing('user');

        return [
            'instructor' => [
                'id' => $this->instructor->id,
                'name' => $this->instructor->user?->name,
                'email' => $this->instructor->user?->email,
                'major' => $this->instructor->major,
            ],
            'creator' => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                // roleSlug() devuelve 'admin' | 'coordinator' | 'instructor' (slug interno).
                'role' => $this->creator->roleSlug(),
                // roleDisplayLabel() devuelve el texto en español para mostrar.
                'role_label' => $this->creator->roleDisplayLabel(),
            ],
            // now() = momento exacto en que se generó la notificación.
            'created_at' => now()->toIso8601String(),
        ];
    }
}
