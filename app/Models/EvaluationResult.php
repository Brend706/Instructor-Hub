<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un EvaluationResult representa UNA evaluación completa (todas las respuestas
 * de un cuestionario) hecha a un instructor en cierto assignment.
 *
 *  - source='internal_system': vino del formulario web (autoeval / coordinador)
 *  - source='forms':           vino de Microsoft Forms importado
 *  - source='csv_import':      vino de un Excel/CSV subido
 *
 * total_score se calcula al guardar (promedio de score_value de las answers
 * con question_type='score').
 */
class EvaluationResult extends Model
{
    public const SOURCE_INTERNAL = 'internal_system';
    public const SOURCE_FORMS = 'forms';
    public const SOURCE_CSV = 'csv_import';

    protected $fillable = [
        'assignment_id',
        'instructor_id',
        'evaluation_type_id',
        'evaluator_user_id',
        'source',
        'total_score',
        'submitted_at',
        'reviewed_by_admin',
    ];

    protected function casts(): array
    {
        return [
            'total_score' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_by_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<InstructorAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(InstructorAssignment::class, 'assignment_id');
    }

    /**
     * @return BelongsTo<Instructor, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    /**
     * @return BelongsTo<EvaluationType, $this>
     */
    public function evaluationType(): BelongsTo
    {
        return $this->belongsTo(EvaluationType::class);
    }

    /**
     * Usuario que realizó la evaluación (nullable para imports externos).
     *
     * @return BelongsTo<User, $this>
     */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_user_id');
    }

    /**
     * @return HasMany<EvaluationAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(EvaluationAnswer::class);
    }
}
