<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupo de clase (`class_groups`): materia, docente de la materia, ciclo, modalidad, horario y aula/enlace.
 */
class ClassGroup extends Model
{
    protected $fillable = [
        'name',
        'professor',
        'semester',
        'modality',
        'schedule',
        'classroom',
        'coordinator_id',
    ];

    /**
     * Coordinador propietario del grupo. Si es NULL el grupo es "huérfano"
     * y solo los admins pueden gestionarlo.
     *
     * @return BelongsTo<Coordinator, $this>
     */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Coordinator::class);
    }

    /**
     * Estudiantes del grupo (tabla `students`).
     *
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Asignaciones de instructores tutor al grupo (tabla pivote `instructor_assignments`).
     *
     * @return HasMany<InstructorAssignment, $this>
     */
    public function instructorAssignments(): HasMany
    {
        return $this->hasMany(InstructorAssignment::class);
    }
}
