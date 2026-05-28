<?php

namespace App\Notifications;

use App\Models\EvaluationResult;
use App\Models\Instructor;
use App\Models\InstructorAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notificación para el coordinador cuando un instructor envía (o reenvía)
 * su autoevaluación. Solo se entrega vía `database` (queda guardada en
 * la tabla `notifications` y aparece en la campanita del coordinador).
 *
 * El payload (data) viaja como JSON e incluye:
 *  - instructor:  id, nombre, correo, carrera
 *  - assignment:  id, grupo (nombre), semestre
 *  - result:      id del registro en evaluation_results y score final
 *  - kind:        'self_evaluation.submitted' (para el switch del NotificationController)
 *  - created_at:  ISO 8601
 */
class SelfEvaluationSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public Instructor $instructor,
        public InstructorAssignment $assignment,
        public EvaluationResult $result,
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
        $this->assignment->loadMissing('classGroup');

        return [
            'kind' => 'self_evaluation.submitted',
            'instructor' => [
                'id' => $this->instructor->id,
                'name' => $this->instructor->user?->name,
                'email' => $this->instructor->user?->email,
                'major' => $this->instructor->major,
            ],
            'assignment' => [
                'id' => $this->assignment->id,
                'group_name' => $this->assignment->classGroup?->name,
                'semester' => $this->assignment->classGroup?->semester,
            ],
            'result' => [
                'id' => $this->result->id,
                'total_score' => $this->result->total_score,
            ],
            'created_at' => now()->toIso8601String(),
        ];
    }
}
