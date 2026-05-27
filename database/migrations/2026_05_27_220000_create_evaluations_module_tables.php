<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de Evaluaciones (4 tablas).
 *
 * Flujo:
 *   1. Un `instructor_assignments.status` pasa a "Finalizado".
 *   2. Se habilitan los tipos de evaluación correspondientes (self / coordinator
 *      / student / teacher) y, según quién evalúa, se crea un `evaluation_results`.
 *   3. Cada respuesta del formulario se guarda como `evaluation_answers`.
 *   4. Al cerrar el envío se calcula el promedio de los `score_value` y se
 *      guarda en `evaluation_results.total_score`.
 *
 * Catálogo (evaluation_types + evaluation_question_templates) lo siembra el
 * EvaluationsSeeder con preguntas estándar; el admin podrá editarlas en una
 * fase futura.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── evaluation_types ─────────────────────────────────────
        // Cada tipo determina quién evalúa, cuántas preguntas y cómo se importan
        // las respuestas (interno = formulario, csv_import / forms = batch).
        Schema::create('evaluation_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();             // 'self' | 'coordinator' | 'student' | 'teacher'
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── evaluation_question_templates ────────────────────────
        // Las preguntas del cuestionario por tipo. `question_type` define el
        // widget en la UI: 'score' (1..max_score, ej. 1-5) o 'text' (textarea).
        Schema::create('evaluation_question_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_type_id')
                ->constrained('evaluation_types')
                ->cascadeOnDelete();
            $table->text('question_text');
            $table->enum('question_type', ['score', 'text', 'multiple_choice'])
                ->default('score');
            $table->unsignedTinyInteger('max_score')->nullable()->default(5); // solo aplica si question_type='score'
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['evaluation_type_id', 'order_index'], 'eval_qt_type_order_idx');
        });

        // ── evaluation_results ───────────────────────────────────
        // Un registro = una evaluación completa hecha a un instructor/assignment.
        // Para autoevaluación y coordinador el `evaluator_user_id` se llena con
        // el usuario logueado. Para imports masivos (CSV/Forms) queda NULL y
        // se identifica al evaluador vía `source` + filas externas.
        Schema::create('evaluation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')
                ->constrained('instructor_assignments')
                ->cascadeOnDelete();
            $table->foreignId('instructor_id')
                ->constrained('instructors')
                ->cascadeOnDelete();
            $table->foreignId('evaluation_type_id')
                ->constrained('evaluation_types')
                ->cascadeOnDelete();
            $table->foreignId('evaluator_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->enum('source', ['internal_system', 'forms', 'csv_import'])
                ->default('internal_system');
            $table->decimal('total_score', 5, 2)->nullable();      // promedio calculado al cerrar
            $table->timestamp('submitted_at')->nullable();         // cuándo se cerró/envió
            $table->boolean('reviewed_by_admin')->default(false);  // ya lo vio admin?
            $table->timestamps();

            // Una sola autoevaluación / coordinador-evaluación por assignment.
            // Para 'student'/'teacher' importados se permiten varios por assignment.
            $table->index(['assignment_id', 'evaluation_type_id'], 'eval_results_assign_type_idx');
            $table->index('instructor_id', 'eval_results_instructor_idx');
        });

        // ── evaluation_answers ───────────────────────────────────
        // Cada fila es la respuesta a UNA pregunta dentro de un result.
        // Solo se llena la columna correspondiente al question_type del template.
        Schema::create('evaluation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_result_id')
                ->constrained('evaluation_results')
                ->cascadeOnDelete();
            $table->foreignId('question_template_id')
                ->constrained('evaluation_question_templates')
                ->cascadeOnDelete();
            $table->decimal('score_value', 4, 2)->nullable();
            $table->text('text_value')->nullable();
            $table->string('selected_option')->nullable();
            $table->timestamps();

            // Evita guardar 2 respuestas a la misma pregunta en el mismo result.
            $table->unique(['evaluation_result_id', 'question_template_id'], 'eval_answer_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_answers');
        Schema::dropIfExists('evaluation_results');
        Schema::dropIfExists('evaluation_question_templates');
        Schema::dropIfExists('evaluation_types');
    }
};
