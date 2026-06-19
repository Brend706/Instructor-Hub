<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación para administradores cuando un usuario solicita soporte
 * humano desde FICABOT (porque el bot no pudo resolver su duda).
 *
 * Se entrega solo por el canal `database` y se muestra en la campanita
 * del layout del admin, igual que las notificaciones de instructor creado.
 *
 * Payload (data JSON):
 *  - requester: id, nombre, correo y rol del usuario que pidió soporte.
 *  - question:  el último mensaje que el usuario le escribió a FICABOT.
 *  - bot_reply: opcional, la última respuesta del bot (contexto para el admin).
 *  - created_at: ISO 8601.
 */
class FicabotSupportRequested extends Notification
{
    use Queueable;

    /**
     * @param  ?User   $requester     Usuario logueado que pide hablar con un admin (null si la sesión expiró).
     * @param  string  $question      Pregunta original que disparó el escalado.
     * @param  ?string $botReply      Última respuesta del bot, para dar contexto al admin.
     * @param  ?string $contactName   Nombre con el que el usuario quiere ser contactado (puede no coincidir con su cuenta).
     * @param  ?string $contactEmail  Correo de contacto preferido para la respuesta.
     * @param  ?string $reason        Motivo que el usuario escribió en el formulario de contacto.
     */
    public function __construct(
        public ?User $requester,
        public string $question,
        public ?string $botReply = null,
        public ?string $contactName = null,
        public ?string $contactEmail = null,
        public ?string $reason = null,
    ) {}

    /**
     * Canal de entrega: solo BD (no correo, no broadcast).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Estructura JSON guardada en `notifications.data`.
     * Se lee desde el dropdown del admin en `layouts.admin`.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            // Tipo lógico para que el frontend pueda distinguir esta notif de InstructorCreated.
            'kind' => 'ficabot.support',
            // Cuenta con la que el usuario está logueado (para auditoría).
            // Puede ser null si la sesión del usuario había expirado al momento del envío.
            'requester' => $this->requester !== null ? [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
                'email' => $this->requester->email,
                'role' => $this->requester->roleSlug(),
                'role_label' => $this->requester->roleDisplayLabel(),
            ] : null,
            // Datos preferidos de contacto: pueden diferir del usuario logueado
            // (por ejemplo, si el usuario quiere que le respondan a otro correo).
            'contact' => [
                'name' => $this->contactName ?? $this->requester?->name ?? 'Sin nombre',
                'email' => $this->contactEmail ?? $this->requester?->email ?? 'sin-correo@local',
            ],
            // Motivo escrito por el usuario en el formulario de contacto.
            'reason' => $this->reason !== null ? mb_substr($this->reason, 0, 1000) : null,
            // Truncamos el texto para evitar JSONs gigantes en la tabla notifications.
            'question' => mb_substr($this->question, 0, 1000),
            'bot_reply' => $this->botReply !== null
                ? mb_substr($this->botReply, 0, 1000)
                : null,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
