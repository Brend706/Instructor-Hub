<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Relación instructor ↔ grupo (`instructor_assignments`).
 * La UI muestra un instructor por grupo; si hay varias filas, se usa la primera cargada.
 */
class InstructorAssignment extends Model
{
    protected $fillable = [
        'instructor_id',
        'class_group_id',
        'schedule',
        'status',
        'modality',
        'classroom',
        'virtual_link',
        'admin_student_verdict',
    ];

    /**
     * @return BelongsTo<Instructor, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * @return BelongsTo<ClassGroup, $this>
     */
    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    /**
     * @return HasMany<ClassSession, $this>
     */
    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }
}
