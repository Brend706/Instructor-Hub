<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una respuesta concreta dentro de un EvaluationResult.
 * Según el question_type del template, se llena score_value o text_value.
 */
class EvaluationAnswer extends Model
{
    protected $fillable = [
        'evaluation_result_id',
        'question_template_id',
        'score_value',
        'text_value',
        'selected_option',
    ];

    protected function casts(): array
    {
        return ['score_value' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<EvaluationResult, $this>
     */
    public function result(): BelongsTo
    {
        return $this->belongsTo(EvaluationResult::class, 'evaluation_result_id');
    }

    /**
     * @return BelongsTo<EvaluationQuestionTemplate, $this>
     */
    public function questionTemplate(): BelongsTo
    {
        return $this->belongsTo(EvaluationQuestionTemplate::class, 'question_template_id');
    }
}
