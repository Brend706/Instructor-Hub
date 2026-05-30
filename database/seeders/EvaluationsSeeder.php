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
                'description' => 'Respuestas de los estudiantes, importadas desde Excel/Forms.',
                'questions' => $this->studentQuestions(),
            ],
            [
                'slug' => EvaluationType::TEACHER,
                'name' => 'Evaluación del docente titular',
                'description' => 'El docente titular del grupo evalúa al instructor (importado).',
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
            $this->score('Asiste o se conecta puntualmente a las clases como los demás estudiantes.'),
            $this->score('Planifica sus instructorías según el formato de Sesión de Instructoría.'),
            $this->score('Envía oportunamente la planificación al Coordinador de instructores.'),

            $this->text('¿Cuáles herramientas tecnológicas utiliza para impartir los contenidos de la instructoría y las actividades evaluadas?'),
            $this->text('¿Cuáles recursos digitales utiliza para impartir sus instructorías?'),
            $this->text('¿Cuál o cuáles estrategias metodológicas aplica para impartir su instructoría?'),

            $this->score('Explica a los estudiantes la competencia y los elementos de competencia a desarrollar en la asignatura y en la instructoría.'),
            $this->score('Motiva a los estudiantes para que adquieran el dominio de las habilidades, conocimientos y actitudes.'),
            $this->score('Brinda apoyo y resuelve dudas sobre los contenidos que el profesor desarrolla en la clase.'),
            $this->score('Cumple con el horario de instructoría que se ha establecido.'),
            $this->score('Responde mensajería o correos oportunamente (en un período no mayor a 24 horas).'),
            $this->score('Mantiene con el Docente, Estudiantes y Coordinador una buena comunicación basada en la responsabilidad, respeto y confianza.'),
            $this->score('Trabaja en equipo con el Docente para desarrollar en los estudiantes las competencias que demanda la asignatura.'),
            $this->score('Presenta materiales, ejemplos y ejercicios idóneos para desarrollar las competencias establecidas en el Diseño Instruccional de la asignatura.'),
            $this->score('Los contenidos vistos en la instructoría son coherentes con los contenidos evaluados en el parcial.'),
            $this->score('Asiste a reuniones, actividades académicas y administrativas según convocatoria del Coordinador de Instructores.'),
            $this->score('Tiene vocación para ejercer la labor docente.'),
        ];
    }

    /** @return array<int, array{text:string,type:string}> */
    private function coordinatorQuestions(): array
    {
        return [
            $this->score('El instructor asiste o se conecta puntualmente a las clases como los demás estudiantes.'),
            $this->score('El instructor planifica sus instructorías según el formato de Sesión de Instructoría.'),
            $this->score('El instructor utiliza las herramientas tecnológicas y recursos didácticos adecuados para desarrollar en los estudiantes las competencias que demanda la asignatura.'),
            $this->score('El instructor implementa las estrategias metodológicas adecuadas para desarrollar en los estudiantes las competencias que demanda la asignatura.'),
            $this->score('El instructor envía al Coordinador oportunamente la planificación de las instructorías.'),
            $this->score('El instructor responde al Coordinador, mensajería o correos oportunamente (en un período no mayor a 24 horas).'),
            $this->score('El instructor mantiene con el Coordinador una buena comunicación basada en la responsabilidad, respeto y confianza.'),
            $this->score('El instructor asiste a reuniones, actividades académicas y administrativas, según convocatoria del Coordinador.'),
            $this->score('Se observa que el instructor tiene vocación para ejercer la labor docente.'),
        ];
    }

    /** @return array<int, array{text:string,type:string}> */
    private function studentQuestions(): array
{
    return [
        $this->text(
            'Herramientas tecnológicas que el instructor utiliza para impartir los contenidos de la instructoría y las actividades evaluadas.'
        ),

        $this->text(
            'Recursos digitales que el instructor utiliza.'
        ),

        $this->text(
            'Estrategia metodológica que el instructor aplica.'
        ),

        $this->score(
            'El instructor asiste o se conecta puntualmente a las clases como los demás estudiantes.'
        ),

        $this->score(
            'Al inicio de la instructoría, el instructor presentó la competencia y los elementos de competencia que se esperan desarrollar en la asignatura (habilidades, conocimientos y actitudes).'
        ),

        $this->score(
            'El instructor motiva a los estudiantes para que adquieran el dominio de las habilidades, conocimientos y actitudes.'
        ),

        $this->score(
            'El instructor brinda apoyo y resuelve dudas sobre los contenidos que el profesor desarrolla en la clase.'
        ),

        $this->score(
            'El instructor cumple con el horario de instructoría que se ha establecido.'
        ),

        $this->score(
            'El instructor responde mensajería o correos oportunamente (en un período no mayor a 24 horas).'
        ),

        $this->score(
            'El instructor mantiene una comunicación respetuosa con los estudiantes.'
        ),

        $this->score(
            'El instructor presenta materiales, ejemplos y ejercicios idóneos para desarrollar las competencias establecidas en el Diseño Instruccional de la asignatura.'
        ),

        $this->score(
            'Los contenidos desarrollados en la instructoría son coherentes con los contenidos evaluados en el parcial.'
        ),

        $this->score(
            'Se observa que el instructor tiene vocación para ejercer la labor docente.'
        ),

        $this->text(
            'Comentarios generales sobre su experiencia en esta instructoría. Pueden ser sugerencias o comentarios generales para mejorar el proceso de instructorías.'
        ),
    ];
}

    /** @return array<int, array{text:string,type:string}> */
    private function teacherQuestions(): array
    {
        return [
            $this->text('Al inicio del ciclo académico, el docente facilitó el Diseño Instruccional al instructor.'),

            $this->score('El instructor asiste o se conecta puntualmente a las clases como los demás estudiantes.'),
            $this->score('El instructor brinda apoyo y resuelve dudas sobre los contenidos que el docente desarrolla en la clase.'),
            $this->score('El instructor responde mensajería o correos oportunamente (en un período no mayor a 24 horas).'),
            $this->score('El instructor mantiene buena comunicación con el docente basada en la responsabilidad, respeto y confianza.'),
            $this->score('El Instructor trabaja en equipo con el Docente para desarrollar en los estudiantes las competencias que demanda la asignatura.'),
            $this->score('El instructor apoya al docente en las actividades académicas y administrativas que le corresponden.'),
            $this->score('Se observa que el instructor tiene vocación para ejercer la labor docente.'),

            $this->text('Comentarios generales sobre el desempeño del instructor. Pueden ser sugerencias o comentarios para mejorar el proceso de instructorías.'),
        ];
    }
}
