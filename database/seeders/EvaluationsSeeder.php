<?php

namespace Database\Seeders;

use App\Models\EvaluationQuestionTemplate;
use App\Models\EvaluationType;
use Illuminate\Database\Seeder;

/**
 * Siembra los 4 tipos de evaluación y sus preguntas estándar:
 *   - self        (19 preguntas: 15 score + 4 texto)
 *   - coordinator (10 preguntas: 7 score + 3 texto)
 *   - student     (10 preguntas: 8 score + 2 texto)  ← se importan via Excel
 *   - teacher     (10 preguntas: 7 score + 3 texto)  ← se importan via Excel
 *
 * Es idempotente: si los tipos ya existen, solo se actualizan. Si las
 * preguntas ya existen para un tipo, no se duplican.
 */
class EvaluationsSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'slug' => EvaluationType::SELF,
                'name' => 'Autoevaluación',
                'description' => 'El instructor evalúa su propio desempeño al cerrar el ciclo.',
                'questions' => $this->selfQuestions(),
            ],
            [
                'slug' => EvaluationType::COORDINATOR,
                'name' => 'Evaluación del coordinador',
                'description' => 'El coordinador evalúa el desempeño del instructor a su cargo.',
                'questions' => $this->coordinatorQuestions(),
            ],
            [
                'slug' => EvaluationType::STUDENT,
                'name' => 'Evaluación de estudiantes',
                'description' => 'Respuestas anónimas de los estudiantes, importadas desde Excel/Forms.',
                'questions' => $this->studentQuestions(),
            ],
            [
                'slug' => EvaluationType::TEACHER,
                'name' => 'Evaluación del docente titular',
                'description' => 'El docente titular del curso evalúa al instructor (importado).',
                'questions' => $this->teacherQuestions(),
            ],
        ];

        foreach ($types as $data) {
            $type = EvaluationType::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'is_active' => true,
                ]
            );

            foreach ($data['questions'] as $index => $q) {
                EvaluationQuestionTemplate::updateOrCreate(
                    [
                        'evaluation_type_id' => $type->id,
                        'order_index' => $index + 1,
                    ],
                    [
                        'question_text' => $q['text'],
                        'question_type' => $q['type'],
                        'max_score' => $q['type'] === 'score' ? 5 : null,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * Helper: arma una pregunta tipo score con la escala por defecto (1..5).
     *
     * @return array{text:string,type:string}
     */
    private function score(string $text): array
    {
        return ['text' => $text, 'type' => EvaluationQuestionTemplate::TYPE_SCORE];
    }

    /**
     * Helper: arma una pregunta de texto libre.
     *
     * @return array{text:string,type:string}
     */
    private function text(string $text): array
    {
        return ['text' => $text, 'type' => EvaluationQuestionTemplate::TYPE_TEXT];
    }

    /** @return array<int, array{text:string,type:string}> */
    private function selfQuestions(): array
    {
        return [
            $this->score('Cumplí con los horarios de instructoría acordados.'),
            $this->score('Preparé adecuadamente el material para cada sesión.'),
            $this->score('Logré que los estudiantes comprendieran los temas.'),
            $this->score('Atendí las dudas de los estudiantes con claridad.'),
            $this->score('Mantuve una buena comunicación con el docente titular.'),
            $this->score('Fomenté la participación activa de los estudiantes.'),
            $this->score('Apliqué estrategias adecuadas a distintos ritmos de aprendizaje.'),
            $this->score('Generé un ambiente de respeto y confianza.'),
            $this->score('Cumplí con los temas planificados del ciclo.'),
            $this->score('Identifiqué a tiempo a estudiantes con dificultades.'),
            $this->score('Apliqué actividades de refuerzo cuando fue necesario.'),
            $this->score('Usé recursos visuales o digitales adecuados.'),
            $this->score('Acepté retroalimentación de la coordinación.'),
            $this->score('Documenté correctamente la asistencia.'),
            $this->score('Demostré dominio del tema durante las instructorías.'),
            $this->text('¿Qué aspecto destacarías de tu instructoría?'),
            $this->text('¿Qué dificultades encontraste?'),
            $this->text('¿Qué mejorarías para el próximo ciclo?'),
            $this->text('Observaciones adicionales.'),
        ];
    }

    /** @return array<int, array{text:string,type:string}> */
    private function coordinatorQuestions(): array
    {
        return [
            $this->score('Puntualidad en los horarios de instructoría.'),
            $this->score('Calidad del material preparado.'),
            $this->score('Dominio del tema.'),
            $this->score('Trato y comunicación con los estudiantes.'),
            $this->score('Comunicación con la coordinación.'),
            $this->score('Cumplimiento de los temas planificados.'),
            $this->score('Compromiso general con la instructoría.'),
            $this->text('Fortalezas que destacarías del instructor.'),
            $this->text('Áreas de mejora.'),
            $this->text('Observaciones generales.'),
        ];
    }

    /** @return array<int, array{text:string,type:string}> */
    private function studentQuestions(): array
    {
        return [
            $this->score('El instructor explica con claridad.'),
            $this->score('Resuelve mis dudas adecuadamente.'),
            $this->score('Es puntual en las instructorías.'),
            $this->score('Demuestra dominio del tema.'),
            $this->score('Fomenta la participación.'),
            $this->score('Me trata con respeto.'),
            $this->score('Recomendaría al instructor a otros compañeros.'),
            $this->score('Calificación general del instructor.'),
            $this->text('¿Qué te gustó de la instructoría?'),
            $this->text('¿Qué mejorarías?'),
        ];
    }

    /** @return array<int, array{text:string,type:string}> */
    private function teacherQuestions(): array
    {
        return [
            $this->score('Cumplió con el plan de trabajo acordado.'),
            $this->score('Demostró dominio del tema.'),
            $this->score('Mantuvo buena coordinación con el docente.'),
            $this->score('Brindó apoyo de calidad a los estudiantes.'),
            $this->score('Mostró disponibilidad y compromiso.'),
            $this->score('Capacidad de explicación clara.'),
            $this->score('Tuvo iniciativa propia.'),
            $this->text('Aportes destacados del instructor.'),
            $this->text('Áreas de mejora.'),
            $this->text('Observaciones generales.'),
        ];
    }
}
