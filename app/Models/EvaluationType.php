<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de tipos de evaluación (autoevaluación, coordinador, estudiante, docente).
 * Cada tipo agrupa su set de preguntas y los results derivados.
 */
class EvaluationType extends Model
{
    public const SELF = 'self';
    public const COORDINATOR = 'coordinator';
    public const STUDENT = 'student';
    public const TEACHER = 'teacher';

    protected $fillable = ['slug', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<EvaluationQuestionTemplate, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(EvaluationQuestionTemplate::class)
            ->where('is_active', true)
            ->orderBy('order_index');
    }

    /**
     * @return HasMany<EvaluationResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(EvaluationResult::class);
    }
}
