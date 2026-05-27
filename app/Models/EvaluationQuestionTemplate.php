<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cada fila es una pregunta del cuestionario de cierto tipo de evaluación.
 * - question_type='score'  → el form muestra una escala 1..max_score
 * - question_type='text'   → textarea libre
 * - question_type='multiple_choice' → reservado para futuro
 */
class EvaluationQuestionTemplate extends Model
{
    public const TYPE_SCORE = 'score';
    public const TYPE_TEXT = 'text';
    public const TYPE_MULTIPLE = 'multiple_choice';

    protected $fillable = [
        'evaluation_type_id',
        'question_text',
        'question_type',
        'max_score',
        'order_index',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'max_score' => 'integer',
            'order_index' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<EvaluationType, $this>
     */
    public function evaluationType(): BelongsTo
    {
        return $this->belongsTo(EvaluationType::class);
    }

    /**
     * Respuestas concretas vinculadas a esta plantilla (todas las que ya se
     * registraron a lo largo del tiempo).
     *
     * @return HasMany<EvaluationAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(EvaluationAnswer::class, 'question_template_id');
    }
}
