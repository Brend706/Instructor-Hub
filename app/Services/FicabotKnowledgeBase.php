<?php

namespace App\Services;

/**
 * Base de conocimiento de Lumi (alias técnico FICABOT) — rule-based, sin OpenAI.
 *
 *  ═══════════════════════════════════════════════════════════════════
 *  MANIFIESTO DE PERSONALIDAD DE LUMI
 *  ═══════════════════════════════════════════════════════════════════
 *  Lumi es el asistente del sistema Instructor Hub. Cuando escribas o
 *  edites respuestas, mantené este tono y estas reglas SIEMPRE:
 *
 *  Personalidad:
 *      - Cordial, cercana, moderna. Habla en español neutro.
 *      - Transmite confianza, rapidez, apoyo y claridad.
 *      - NUNCA suena robótica ni excesivamente formal.
 *
 *  Estilo de respuesta:
 *      - Lenguaje sencillo. Evita tecnicismos innecesarios.
 *      - Pasos numerados cuando hay que explicar un proceso (máx. 5 pasos).
 *      - Usa los nombres LITERALES de menús, botones y mensajes del sistema.
 *      - Ofrece ayuda adicional al final cuando aplique
 *        (p. ej. "¿Te ayudo con algo más?" o una pregunta de seguimiento).
 *      - Respuestas concisas: idealmente ≤ 150 palabras.
 *
 *  Reglas estrictas (cosas que Lumi NUNCA debe hacer):
 *      - Inventar funcionalidades o información inexistente.
 *      - Decir que ejecutó una acción (Lumi NO ejecuta nada, solo guía).
 *      - Modificar o consultar datos privados del usuario.
 *      - Responder fuera del contexto académico/técnico de Instructor Hub.
 *      - Compartir información sensible (contraseñas, tokens, datos de otros).
 *
 *  Manejo de errores y dudas no cubiertas:
 *      - Pedir al usuario que cuente más detalles (mensaje exacto, pantalla
 *        donde está, qué intentaba hacer).
 *      - Si aun así no hay respuesta clara, ofrecer escalar al administrador
 *        usando la frase canónica "¿Querés que te ponga en contacto con un
 *        administrador?" (el widget la detecta y muestra el botón).
 *
 *  Detección por rol:
 *      - El servicio le pasa a resolve() el rol del usuario logueado
 *        (admin / coordinator / instructor / null).
 *      - Si un intent define `responses[role]`, gana esa respuesta.
 *      - Si un intent define `restricted_to` y el rol no está, devuelve
 *        `restricted_response` y activa el escalado automáticamente.
 *  ═══════════════════════════════════════════════════════════════════
 *
 *  Estructura de cada intent (todas las claves opcionales salvo id y keywords):
 *    - id (string):                  identificador unico.
 *    - keywords (array<string>):     formas comunes de preguntar lo mismo,
 *                                    sin acentos y en minusculas.
 *    - response (string):            respuesta por defecto.
 *    - responses (array<role,string>): respuesta especifica por rol.
 *                                    Si existe la del rol actual, gana sobre
 *                                    `response`.
 *    - suggestions (array<string>):  chips de seguimiento por defecto.
 *    - suggestions_by_role:          chips por rol; ganan sobre `suggestions`.
 *    - restricted_to (array<role>):  si esta presente y el rol del usuario
 *                                    NO esta en la lista, el bot devuelve
 *                                    `restricted_response` + activa el boton
 *                                    "Contactar administrador".
 *    - restricted_response (string): mensaje que se muestra cuando el rol
 *                                    no esta permitido. Debe terminar con la
 *                                    frase de escalado para que el widget
 *                                    monte el boton de contacto.
 *
 *  Como agregar respuestas nuevas:
 *    1) Edita loadIntents() y agrega un bloque con un id unico.
 *    2) Lista todas las formas en las que puede preguntarse (keywords).
 *    3) Si el contenido cambia segun el rol, usa `responses` por rol.
 *    4) Si solo cierto rol puede hacerlo, usa `restricted_to`.
 *
 *  Roles validos en el sistema: 'admin', 'coordinator', 'instructor'.
 *  Cuando el usuario no esta logueado, el rol queda como null y el bot
 *  responde como "invitado" (suelen ver respuestas restringidas).
 */
class FicabotKnowledgeBase
{
    /** @var array<int, array<string, mixed>> */
    private array $intents;

    public function __construct()
    {
        $this->intents = $this->loadIntents();
    }

    /**
     * Busca el intent que mejor coincide con el texto del usuario.
     *
     * Algoritmo de scoring: para cada keyword del intent que sea substring
     * del texto normalizado, sumamos la LONGITUD de la keyword al score.
     * Así un keyword muy específico ("finalizar sesion qr", 19 chars) pesa
     * mucho más que uno genérico ("qr", 2 chars). Esto evita que preguntas
     * como "¿cómo finalizo la sesión QR?" caigan en start_qr_session solo
     * porque la palabra "qr" estaba ahí.
     *
     * @return array<string, mixed>|null
     */
    public function match(string $message): ?array
    {
        $normalized = $this->normalize($message);
        if ($normalized === '') {
            return null;
        }

        $bestScore = 0;
        $bestIntent = null;

        foreach ($this->intents as $intent) {
            $score = 0;
            foreach ($intent['keywords'] as $keyword) {
                $kw = $this->normalize($keyword);
                if ($kw !== '' && str_contains($normalized, $kw)) {
                    $score += mb_strlen($kw);
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIntent = $intent;
            }
        }

        return $bestScore > 0 ? $bestIntent : null;
    }

    /**
     * Resuelve un intent contra el rol del usuario y devuelve la respuesta
     * lista para mandar al frontend, junto con sugerencias y si conviene
     * mostrar el boton "Contactar administrador".
     *
     * @param  array<string, mixed>  $intent
     * @return array{reply:string, suggestions:array<int,string>, can_escalate:bool, restricted:bool}
     */
    public function resolve(array $intent, ?string $role): array
    {
        $isRestricted = isset($intent['restricted_to'])
            && is_array($intent['restricted_to'])
            && ! in_array($role, $intent['restricted_to'], true);

        if ($isRestricted) {
            $reply = $intent['restricted_response'] ?? $this->genericRestrictedMessage();
            $suggestions = $this->pickSuggestions($intent, $role);

            return [
                'reply' => $reply,
                'suggestions' => $suggestions,
                'can_escalate' => true,
                'restricted' => true,
            ];
        }

        // Respuesta por rol > respuesta por defecto.
        $reply = null;
        if (isset($intent['responses'][$role]) && is_string($intent['responses'][$role])) {
            $reply = $intent['responses'][$role];
        }
        if ($reply === null || $reply === '') {
            $reply = $intent['response'] ?? $this->genericNotFoundMessage();
        }

        return [
            'reply' => $reply,
            'suggestions' => $this->pickSuggestions($intent, $role),
            'can_escalate' => $this->mentionsEscalation($reply),
            'restricted' => false,
        ];
    }

    /**
     * Devuelve los chips de sugerencias resueltos: primero por rol del intent,
     * despues los genericos del intent, y finalmente los por defecto del rol.
     *
     * @param  array<string, mixed>  $intent
     * @return array<int, string>
     */
    private function pickSuggestions(array $intent, ?string $role): array
    {
        if (isset($intent['suggestions_by_role'][$role]) && is_array($intent['suggestions_by_role'][$role])) {
            return $intent['suggestions_by_role'][$role];
        }
        if (isset($intent['suggestions']) && is_array($intent['suggestions']) && $intent['suggestions'] !== []) {
            return $intent['suggestions'];
        }

        return $this->defaultSuggestions($role);
    }

    /**
     * Sugerencias por defecto cuando un intent no define propias.
     * Tambien se usan al abrir el chat por primera vez.
     *
     * @return array<int, string>
     */
    public function defaultSuggestions(?string $role): array
    {
        return match ($role) {
            'admin' => [
                '¿Cómo creo un coordinador?',
                '¿Cómo veo todas las evaluaciones?',
                '¿Cómo gestiono las preguntas de evaluación?',
            ],
            'coordinator' => [
                '¿Cómo creo un grupo de clase?',
                '¿Cómo evalúo a un instructor?',
                '¿Cómo importo evaluaciones de estudiantes?',
            ],
            'instructor' => [
                '¿Cómo inicio una sesión con QR?',
                '¿Cómo envío mi autoevaluación?',
                '¿Cómo exporto la asistencia a Excel?',
            ],
            default => [
                '¿Cómo creo una cuenta?',
                '¿Cómo marco mi asistencia?',
                '¿Cómo contacto al administrador?',
            ],
        };
    }

    /**
     * Chips iniciales (pantalla vacia) y "topic pills" del widget,
     * adaptados al rol del usuario logueado. Cada chip tiene:
     *   - text: lo que aparece al usuario (y se envia como pregunta).
     *   - icon: 'info', 'lock', 'star', 'qr', etc. (el widget lo mapea a un SVG).
     *
     * @return array<int, array{text:string, icon:string}>
     */
    public function initialChips(?string $role): array
    {
        return match ($role) {
            'admin' => [
                ['text' => '¿Cómo creo una cuenta de coordinador?', 'icon' => 'info'],
                ['text' => '¿Cómo veo las notificaciones de la campanita?', 'icon' => 'bell'],
                ['text' => '¿Cómo elimino un instructor?', 'icon' => 'trash'],
            ],
            'coordinator' => [
                ['text' => '¿Cómo creo un grupo de clase?', 'icon' => 'star'],
                ['text' => '¿Cómo importo los estudiantes de un grupo?', 'icon' => 'upload'],
                ['text' => '¿Cómo asigno un instructor a un grupo?', 'icon' => 'link'],
            ],
            'instructor' => [
                ['text' => '¿Cómo inicio una sesión con QR?', 'icon' => 'qr'],
                ['text' => '¿Cómo veo la matriz de asistencia?', 'icon' => 'list'],
                ['text' => '¿Cómo exporto mi asistencia a Excel?', 'icon' => 'download'],
            ],
            default => [
                ['text' => '¿Cómo creo una cuenta de instructor?', 'icon' => 'info'],
                ['text' => '¿Qué hago si no puedo iniciar sesión?', 'icon' => 'lock'],
                ['text' => '¿Cómo registro los estudiantes de un grupo?', 'icon' => 'star'],
            ],
        };
    }

    /**
     * "Topic pills" abajo del bloque de chips iniciales. Cambian segun rol
     * para que cada quien vea los temas que realmente le aplican.
     *
     * @return array<int, string>
     */
    public function topicPills(?string $role): array
    {
        return match ($role) {
            'admin' => ['Coordinadores', 'Instructores', 'Evaluaciones', 'Notificaciones'],
            'coordinator' => ['Mis instructores', 'Grupos de clase', 'Instructorías', 'Evaluaciones'],
            'instructor' => ['Mis grupos', 'Iniciar QR', 'Asistencia', 'Autoevaluación'],
            default => ['Iniciar sesión', 'Asistencia QR', 'Mi perfil', 'Contactar admin'],
        };
    }

    /**
     * Normaliza texto para el matching:
     *   - minúsculas, sin acentos, sin signos de puntuación
     *   - sustituye verbos en 1ª persona del singular por su infinitivo
     *     (creo→crear, edito→editar, exporto→exportar, etc.). Así las
     *     keywords del KB pueden quedar SIEMPRE en infinitivo y matchean
     *     también cuando el usuario escribe "¿Cómo creo X?" o "¿Cómo
     *     elimino Y?". Sin esto, había que duplicar cada keyword.
     */
    public function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ñ' => 'n', 'ü' => 'u', 'ç' => 'c',
            '¿' => '', '?' => '', '¡' => '', '!' => '',
            '.' => ' ', ',' => ' ', ';' => ' ', ':' => ' ',
        ]);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        // Sustitución palabra a palabra:
        //   1) verbos en 1ª persona singular y 3ª persona singular/plural
        //      -> infinitivo (creo→crear, importan→importar, etc.). Así
        //      las keywords del KB pueden quedar SIEMPRE en infinitivo y
        //      matchean independientemente de la conjugación que use el
        //      usuario al preguntar.
        //   2) artículos sueltos (un, una, el, la, los, las, al, del) ->
        //      vacío. Así "como crear un coordinador" se reduce a
        //      "como crear coordinador" y matchea la keyword corta
        //      "crear coordinador".
        //
        //  IMPORTANTE: NO eliminamos pronombres posesivos (mi, tu, su)
        //  porque distinguen frases como "mi perfil" vs "perfil".
        static $verbMap = [
            // 1ª persona singular
            'creo' => 'crear', 'edito' => 'editar', 'elimino' => 'eliminar',
            'borro' => 'borrar', 'cambio' => 'cambiar', 'actualizo' => 'actualizar',
            'modifico' => 'modificar', 'exporto' => 'exportar', 'descargo' => 'descargar',
            'importo' => 'importar', 'subo' => 'subir', 'asigno' => 'asignar',
            'desasigno' => 'desasignar', 'finalizo' => 'finalizar', 'inicio' => 'iniciar',
            'termino' => 'terminar', 'cierro' => 'cerrar', 'genero' => 'generar',
            'reviso' => 'revisar', 'imprimo' => 'imprimir', 'tomo' => 'tomar',
            'marco' => 'marcar', 'registro' => 'registrar', 'guardo' => 'guardar',
            'contacto' => 'contactar', 'reporto' => 'reportar', 'veo' => 'ver',
            'recupero' => 'recuperar', 'reseteo' => 'resetear', 'envio' => 'enviar',
            'agrego' => 'agregar', 'anado' => 'anadir', 'quito' => 'quitar',
            'solicito' => 'solicitar', 'recibo' => 'recibir',
            // 3ª persona singular (lo hace, lo crea, lo edita, etc.)
            'crea' => 'crear', 'edita' => 'editar', 'elimina' => 'eliminar',
            'borra' => 'borrar', 'cambia' => 'cambiar', 'actualiza' => 'actualizar',
            'modifica' => 'modificar', 'exporta' => 'exportar', 'descarga' => 'descargar',
            'importa' => 'importar', 'sube' => 'subir', 'asigna' => 'asignar',
            'finaliza' => 'finalizar', 'inicia' => 'iniciar', 'cierra' => 'cerrar',
            'genera' => 'generar', 'revisa' => 'revisar', 'toma' => 'tomar',
            'marca' => 'marcar', 'registra' => 'registrar', 'guarda' => 'guardar',
            'contacta' => 'contactar', 'reporta' => 'reportar', 'recibe' => 'recibir',
            'envia' => 'enviar', 'agrega' => 'agregar', 'quita' => 'quitar',
            'solicita' => 'solicitar', 'recupera' => 'recuperar',
            // 3ª persona plural (los crean, las importan, etc.)
            'crean' => 'crear', 'editan' => 'editar', 'eliminan' => 'eliminar',
            'borran' => 'borrar', 'cambian' => 'cambiar', 'actualizan' => 'actualizar',
            'exportan' => 'exportar', 'descargan' => 'descargar', 'importan' => 'importar',
            'suben' => 'subir', 'asignan' => 'asignar', 'finalizan' => 'finalizar',
            'inician' => 'iniciar', 'cierran' => 'cerrar', 'generan' => 'generar',
            'ven' => 'ver', 'toman' => 'tomar', 'marcan' => 'marcar',
            'registran' => 'registrar', 'contactan' => 'contactar', 'reciben' => 'recibir',
            'envian' => 'enviar', 'quitan' => 'quitar',
        ];
        static $articles = [
            'un' => '', 'una' => '', 'unos' => '', 'unas' => '',
            'el' => '', 'la' => '', 'los' => '', 'las' => '',
            'al' => '', 'del' => '',
        ];
        $words = explode(' ', $text);
        $clean = [];
        foreach ($words as $w) {
            if ($w === '') {
                continue;
            }
            if (isset($verbMap[$w])) {
                $w = $verbMap[$w];
            }
            if (isset($articles[$w])) {
                continue; // articulo suelto -> se omite
            }
            $clean[] = $w;
        }

        return implode(' ', $clean);
    }

    /**
     * Heuristica: detecta si la respuesta menciona el ofrecimiento de
     * escalado para que el widget muestre el boton "Contactar administrador".
     */
    private function mentionsEscalation(string $reply): bool
    {
        $needle = mb_strtolower($reply);
        $triggers = [
            '¿querés que te ponga en contacto con un administrador?',
            'contactar al administrador',
            'contactar a un administrador',
            'contacta a un administrador',
        ];
        foreach ($triggers as $t) {
            if (str_contains($needle, $t)) {
                return true;
            }
        }

        return false;
    }

    private function genericRestrictedMessage(): string
    {
        return 'Esa acción no está disponible para tu rol actual. '.
            '¿Querés que te ponga en contacto con un administrador?';
    }

    private function genericNotFoundMessage(): string
    {
        return 'No tengo información sobre eso. '.
            '¿Querés que te ponga en contacto con un administrador?';
    }

    /**
     * Catálogo de intents conocidos por FICABOT.
     * Mantén las keywords en minúsculas y SIN acentos: el normalize() del
     * mensaje del usuario ya los elimina antes de comparar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadIntents(): array
    {
        return [

            // ═══════════════════════════════════════════════════════════
            //  CONVERSACIONALES
            // ═══════════════════════════════════════════════════════════

            [
                'id' => 'greeting',
                'keywords' => [
                    'hola', 'buenas', 'buenos dias', 'buenas tardes', 'buenas noches',
                    'que tal', 'saludos', 'hi', 'hello', 'hey', 'klk', 'holi',
                ],
                'response' => "¡Hola! Soy Lumi, tu asistente de Instructor Hub.\n\n"
                    ."Te puedo ayudar con:\n"
                    ."- Coordinadores, instructores y grupos de clase\n"
                    ."- Tomar asistencia con código QR\n"
                    ."- Importar estudiantes desde Excel o CSV\n"
                    ."- Exportar reportes a Excel\n"
                    ."- Tu perfil, contraseña y notificaciones\n\n"
                    ."Elegí un tema o contame tu duda. ¿En qué te ayudo?",
                'suggestions_by_role' => [
                    'admin' => [
                        '¿Cómo creo un coordinador?',
                        '¿Cómo veo las notificaciones?',
                        '¿Cómo elimino un instructor?',
                    ],
                    'coordinator' => [
                        '¿Cómo creo un grupo de clase?',
                        '¿Cómo importo estudiantes?',
                        '¿Cómo asigno un instructor a un grupo?',
                    ],
                    'instructor' => [
                        '¿Cómo inicio una sesión con QR?',
                        '¿Cómo veo la asistencia de mis estudiantes?',
                        '¿Cómo exporto la asistencia a Excel?',
                    ],
                ],
                'suggestions' => [
                    '¿Cómo tomo asistencia con QR?',
                    '¿Cómo importo estudiantes?',
                    '¿Cómo cambio mi contraseña?',
                ],
            ],

            [
                'id' => 'farewell',
                'keywords' => [
                    'gracias', 'muchas gracias', 'mil gracias', 'chao', 'chau',
                    'adios', 'listo', 'ok gracias', 'nos vemos', 'bye',
                ],
                'response' => 'De nada. Si te surge otra duda, abre la burbuja de Lumi cuando quieras.',
                'suggestions' => [],
            ],

            [
                'id' => 'help',
                'keywords' => [
                    'ayuda', 'help', 'que puedes hacer', 'que sabes', 'en que me ayudas',
                    'que haces', 'opciones', 'menu', 'que se puede hacer',
                ],
                'response' => "Estos son los temas en los que te puedo guiar:\n\n"
                    ."- Coordinadores (solo administradores)\n"
                    ."- Instructores: crear, editar, eliminar\n"
                    ."- Grupos de clase: crear, asignar instructor, importar estudiantes\n"
                    ."- Asistencia con QR: iniciar, finalizar, ver matriz\n"
                    ."- Generar reportes en Excel\n"
                    ."- Notificaciones (campanita del admin)\n"
                    ."- Tu perfil, contraseña y cierre de sesión\n"
                    ."- Problemas comunes de login y de carga de Excel\n\n"
                    ."Contame qué necesitas y te paso los pasos. ¿En qué te ayudo?",
                'suggestions' => [
                    '¿Cómo creo un grupo de clase?',
                    '¿Cómo finalizo una sesión QR?',
                    '¿Cómo cambio mi contraseña?',
                ],
            ],

            [
                'id' => 'about_instructor_hub',
                'keywords' => [
                    'que es instructor hub', 'que es esta plataforma', 'para que sirve',
                    'que hace este sistema', 'la fica', 'plataforma fica', 'instructorhub',
                ],
                'response' => "Instructor Hub es la plataforma interna de la Facultad para gestionar instructorías (tutorías académicas):\n\n"
                    ."- Los administradores supervisan todo y reciben notificaciones.\n"
                    ."- Los coordinadores gestionan sus instructores, sus grupos de clase y revisan las instructorías dadas.\n"
                    ."- Los instructores ven sus grupos, inician las sesiones con QR y consultan la asistencia.\n"
                    ."- Los estudiantes escanean el QR y marcan asistencia con su carnet.",
                'suggestions' => [
                    '¿Qué roles existen?',
                    '¿Cómo tomo asistencia con QR?',
                    '¿Cómo exporto la asistencia?',
                ],
            ],

            [
                'id' => 'about_ficabot',
                'keywords' => [
                    'que es ficabot', 'quien es ficabot', 'ficabot',
                    'que es lumi', 'quien es lumi', 'quien sos', 'quien eres',
                    'como te llamas', 'eres una ia', 'eres un bot',
                    'usas openai', 'usas chatgpt', 'usas ia',
                ],
                'response' => "Soy Lumi, el asistente de Instructor Hub. Estoy para guiarte rápido por la plataforma.\n\n"
                    ."Funciono con un banco de respuestas internas (no uso OpenAI), así que no consulto tus datos privados ni necesito internet externo.\n\n"
                    ."Yo solo te oriento: no ejecuto acciones en el sistema, vos seguís los pasos que te indico. Si una duda se me escapa, te pongo en contacto con un administrador con tu nombre y correo. ¿En qué te ayudo?",
                'suggestions' => [
                    '¿Qué roles existen?',
                    '¿Cómo solicito soporte humano?',
                    '¿Qué puedes hacer?',
                ],
            ],

            [
                'id' => 'roles',
                'keywords' => [
                    'roles', 'rol', 'tipos de usuario', 'admin coordinador instructor',
                    'que diferencia hay entre admin', 'permisos', 'que rol soy',
                    'que puede hacer un coordinador', 'que hace un coordinador',
                    'que puede hacer un instructor', 'que hace un instructor',
                    'que puede hacer un admin', 'que hace un admin',
                    'que puede hacer el administrador', 'diferencia entre roles',
                ],
                'response' => "En Instructor Hub hay 3 roles, cada uno con su menú propio:\n\n"
                    ."- Administrador → gestiona coordinadores, todos los instructores y recibe notificaciones en la campanita.\n"
                    ."- Coordinador  → gestiona sus instructores y sus grupos de clase, importa estudiantes y revisa las instructorías.\n"
                    ."- Instructor   → ve sus grupos asignados, inicia sesiones con QR y consulta la matriz de asistencia.\n\n"
                    ."El sistema redirige automáticamente al panel correcto al iniciar sesión.",
                'suggestions' => [
                    '¿Cómo creo un coordinador?',
                    '¿Cómo creo un grupo?',
                    '¿Cómo tomo asistencia con QR?',
                ],
            ],

            // ═══════════════════════════════════════════════════════════
            //  ADMIN — Coordinadores
            // ═══════════════════════════════════════════════════════════

            [
                'id' => 'create_coordinator',
                'keywords' => [
                    'crear coordinador', 'nuevo coordinador', 'agregar coordinador',
                    'registrar coordinador', 'dar de alta coordinador', 'anadir coordinador',
                    'crear cuenta de coordinador', 'crear una cuenta de coordinador',
                    'cuenta de coordinador', 'cuenta coordinador',
                ],
                'restricted_to' => ['admin'],
                'restricted_response' => "Solo el administrador puede crear coordinadores. Si necesitas que se cree una cuenta de coordinador, hay que pedirlo al administrador. "
                    .'¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para crear un coordinador (solo administrador):\n\n"
                    ."1. En el sidebar entra a \"Coordinadores\".\n"
                    ."2. Pulsa el botón azul \"+ Nuevo coordinador\".\n"
                    ."3. Llena nombre, correo, contraseña (mín. 8 caracteres) y la coordinación.\n"
                    ."4. Pulsa \"Guardar\".\n\n"
                    ."Verás el mensaje \"Coordinador creado correctamente.\" y el coordinador podrá entrar con ese correo y contraseña.",
                'suggestions' => [
                    '¿Cómo edito un coordinador?',
                    '¿Cómo elimino un coordinador?',
                    '¿Cómo creo un instructor?',
                ],
            ],

            [
                'id' => 'edit_coordinator',
                'keywords' => [
                    'editar coordinador', 'modificar coordinador', 'actualizar coordinador',
                    'cambiar coordinador', 'cambiar contrasena coordinador', 'reset coordinador',
                ],
                'restricted_to' => ['admin'],
                'restricted_response' => 'Solo el administrador puede editar coordinadores. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para editar un coordinador (solo administrador):\n\n"
                    ."1. Ve a \"Coordinadores\" en el sidebar.\n"
                    ."2. En la fila del coordinador pulsa el icono del lápiz.\n"
                    ."3. Cambia los campos que necesites. Si dejas la contraseña vacía, no se modifica.\n"
                    ."4. Pulsa \"Actualizar\".\n\n"
                    .'Verás el mensaje "Coordinador actualizado correctamente."',
                'suggestions' => [
                    '¿Cómo elimino un coordinador?',
                    '¿Cómo creo un coordinador?',
                    '¿Cómo cambio mi contraseña?',
                ],
            ],

            [
                'id' => 'delete_coordinator',
                'keywords' => [
                    'eliminar coordinador', 'borrar coordinador', 'quitar coordinador',
                    'dar de baja coordinador', 'remover coordinador',
                ],
                'restricted_to' => ['admin'],
                'restricted_response' => 'Solo el administrador puede eliminar coordinadores. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para eliminar un coordinador (solo administrador):\n\n"
                    ."1. Ve a \"Coordinadores\".\n"
                    ."2. En la fila pulsa el icono del bote de basura.\n"
                    ."3. Confirma en el modal \"¿Eliminar coordinador?\" pulsando \"Sí, eliminar\".\n\n"
                    .'La acción no se puede deshacer. Verás el mensaje "Coordinador eliminado correctamente."',
                'suggestions' => [
                    '¿Cómo creo un coordinador?',
                    '¿Cómo edito un coordinador?',
                    '¿Cómo elimino un instructor?',
                ],
            ],

            // ═══════════════════════════════════════════════════════════
            //  ADMIN + COORDINATOR — Instructores
            // ═══════════════════════════════════════════════════════════

            [
                'id' => 'create_instructor',
                'keywords' => [
                    'crear instructor', 'nuevo instructor', 'agregar instructor',
                    'registrar instructor', 'anadir instructor', 'dar de alta instructor',
                    'crear tutor', 'nuevo tutor',
                    'crear cuenta de instructor', 'crear una cuenta de instructor',
                    'cuenta de instructor', 'cuenta instructor',
                ],
                'restricted_to' => ['admin', 'coordinator'],
                'restricted_response' => "Las cuentas de instructor solo las pueden crear un administrador o un coordinador. "
                    ."Si necesitas que te creen una cuenta, decime tus datos y le paso tu solicitud al administrador. "
                    .'¿Querés que te ponga en contacto con un administrador?',
                'responses' => [
                    'admin' => "Para crear un instructor desde el panel admin:\n\n"
                        ."1. Ve a \"Instructores\" en el sidebar.\n"
                        ."2. Pulsa \"+ Nuevo instructor\" arriba a la derecha.\n"
                        ."3. Llena nombre, correo, carrera, estado (Activo/Inactivo) y contraseña temporal (mín. 8 caracteres).\n"
                        ."4. Pulsa \"Guardar\".\n\n"
                        .'Verás "Instructor creado correctamente." Como lo creaste vos mismo (admin), no se envía notificación a otros admins.',
                    'coordinator' => "Para crear un instructor (coordinador):\n\n"
                        ."1. Ve a \"Mis instructores\" en el sidebar.\n"
                        ."2. Pulsa \"+ Nuevo instructor\".\n"
                        ."3. Llena nombre, correo, carrera, estado y contraseña temporal (mín. 8 caracteres).\n"
                        ."4. Pulsa \"Guardar\".\n\n"
                        .'El instructor queda asignado a tu coordinación automáticamente. Cada administrador recibe una notificación en la campanita "Nuevo instructor: {nombre}".',
                ],
                'response' => "Para crear un instructor (admin o coordinador):\n\n"
                    ."1. Entra a \"Instructores\" (admin) o \"Mis instructores\" (coordinador).\n"
                    ."2. Pulsa \"+ Nuevo instructor\".\n"
                    ."3. Completa nombre, correo, carrera, estado y contraseña temporal (mín. 8 caracteres).\n"
                    ."4. Pulsa \"Guardar\".\n\n"
                    .'Si lo crea un coordinador, los administradores reciben una notificación en la campanita.',
                'suggestions' => [
                    '¿Cómo edito un instructor?',
                    '¿Cómo asigno un instructor a un grupo?',
                    '¿Cómo elimino un instructor?',
                ],
            ],

            [
                'id' => 'edit_instructor',
                'keywords' => [
                    'editar instructor', 'modificar instructor', 'cambiar datos instructor',
                    'actualizar instructor', 'editar tutor', 'reset instructor',
                    'cambiar contrasena instructor',
                ],
                'restricted_to' => ['admin', 'coordinator'],
                'restricted_response' => 'Solo administradores y coordinadores pueden editar cuentas de instructor. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para editar un instructor:\n\n"
                    ."1. Ve a \"Instructores\" (admin) o \"Mis instructores\" (coordinador).\n"
                    ."2. En la fila del instructor pulsa el icono del lápiz.\n"
                    ."3. Cambia los datos que necesites. Si dejas la contraseña vacía, no se modifica.\n"
                    ."4. Pulsa \"Actualizar\".\n\n"
                    .'Verás "Instructor actualizado correctamente."',
                'suggestions' => [
                    '¿Cómo elimino un instructor?',
                    '¿Cómo asigno un instructor a un grupo?',
                    '¿Cómo cambio mi contraseña?',
                ],
            ],

            [
                'id' => 'delete_instructor',
                'keywords' => [
                    'eliminar instructor', 'borrar instructor', 'quitar instructor',
                    'no me deja eliminar instructor', 'no se puede eliminar instructor',
                    'error al borrar instructor', 'eliminar tutor', 'borrar tutor',
                    'tiene tutorias asignadas',
                ],
                'restricted_to' => ['admin', 'coordinator'],
                'restricted_response' => 'Solo administradores y coordinadores pueden eliminar instructores. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para eliminar un instructor:\n\n"
                    ."1. Ve a \"Instructores\" o \"Mis instructores\".\n"
                    ."2. En la fila pulsa el icono del bote de basura.\n"
                    ."3. Confirma en el modal \"¿Eliminar instructor?\" con \"Sí, eliminar\".\n\n"
                    ."IMPORTANTE: si el instructor TIENE grupos asignados, el sistema NO te dejará borrarlo y verás el mensaje en rojo:\n"
                    ."\"No se puede eliminar a {nombre} porque tiene N tutoría(s) asignada(s). Quita primero sus asignaciones de grupo antes de borrarlo.\"\n\n"
                    .'En ese caso ve a "Grupos de clase" y cambia el instructor en cada grupo, después vuelve a intentar.',
                'suggestions' => [
                    '¿Cómo asigno o quito un instructor de un grupo?',
                    '¿Cómo creo un instructor?',
                    '¿Cómo edito un instructor?',
                ],
            ],

            [
                'id' => 'view_instructors_list',
                'keywords' => [
                    'ver instructores', 'lista de instructores', 'donde veo los instructores',
                    'mis instructores', 'instructores de mi coordinacion',
                ],
                'responses' => [
                    'admin' => "En el panel admin, en el sidebar entra a \"Instructores\".\n\n"
                        ."Verás la tabla con TODOS los instructores del sistema, su carrera, estado (Activo/Inactivo), y acciones para ver detalle, editar y eliminar.\n\n"
                        .'También podes filtrar por carrera o buscar por nombre/correo.',
                    'coordinator' => "En el panel coordinador, en el sidebar entra a \"Mis instructores\".\n\n"
                        .'Verás los instructores que vos administras (los que tienen tu coordinación) MÁS los instructores aún no asignados a ningún coordinador (huérfanos). Los creados desde el panel admin sin coordinador aparecen aquí hasta que alguien los reclame.',
                    'instructor' => "Como instructor no tienes una lista de \"otros instructores\". Vos solo ves tus propios grupos asignados, en el menú \"Mis grupos\".",
                ],
                'response' => "Para ver el listado de instructores, entra al menú \"Instructores\" (admin) o \"Mis instructores\" (coordinador) en el sidebar.",
                'suggestions' => [
                    '¿Cómo creo un instructor?',
                    '¿Cómo asigno un instructor a un grupo?',
                    '¿Cómo elimino un instructor?',
                ],
            ],

            // ═══════════════════════════════════════════════════════════
            //  ADMIN — Notificaciones (campanita)
            // ═══════════════════════════════════════════════════════════

            [
                'id' => 'view_admin_notifications',
                'keywords' => [
                    'notificaciones', 'campanita', 'campana', 'alertas', 'avisos',
                    'donde veo las notificaciones', 'punto rojo', 'badge rojo',
                ],
                'restricted_to' => ['admin'],
                'restricted_response' => "La campanita de notificaciones solo se muestra en el panel del administrador. "
                    ."Como coordinador o instructor no recibís alertas en campanita. "
                    .'¿Querés que te ponga en contacto con un administrador?',
                'response' => "Las notificaciones aparecen en la campanita arriba a la derecha del panel admin.\n\n"
                    ."Llegan dos tipos:\n"
                    ."- \"Nuevo instructor\": cuando un coordinador crea un instructor.\n"
                    ."- \"Soporte solicitado\": cuando un usuario escala una duda desde FICABOT.\n\n"
                    .'El badge rojo muestra cuántas hay sin leer ("9+" si son más de 9). Hacer clic en una la marca como leída; si es "Nuevo instructor" te lleva a la lista de instructores.',
                'suggestions' => [
                    '¿Cómo marco todas como leídas?',
                    '¿Cómo creo un instructor?',
                    '¿Cómo solicito soporte?',
                ],
            ],

            [
                'id' => 'mark_notifications_read',
                'keywords' => [
                    'marcar leida', 'marcar como leida', 'marcar todas como leidas',
                    'marcar todas las notificaciones', 'todas como leidas',
                    'limpiar notificaciones', 'limpiar campanita', 'leer notificacion',
                ],
                'restricted_to' => ['admin'],
                'restricted_response' => 'Solo el administrador maneja notificaciones en la campanita. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para marcar notificaciones como leídas (admin):\n\n"
                    ."1. Abre la campanita arriba a la derecha.\n"
                    ."2. Click en una notificación → queda leída y, si es \"Nuevo instructor\", te lleva al listado.\n"
                    ."3. Para limpiar TODAS: arriba del listado pulsa \"Marcar todas como leídas\".\n\n"
                    .'El contador del badge se actualiza al recargar.',
                'suggestions' => [
                    '¿Cómo veo las notificaciones?',
                    '¿Cómo creo un instructor?',
                    '¿Cómo solicito soporte?',
                ],
            ],

            // ═══════════════════════════════════════════════════════════
            //  ADMIN — Placeholders del sidebar
            // ═══════════════════════════════════════════════════════════

            [
                'id' => 'admin_placeholders',
                'keywords' => [
                    'reportes', 'reportes admin', 'asistencia admin', 'instructorias admin',
                    'no funciona reportes', 'no carga reportes', 'analisis admin',
                    'donde estan los reportes', 'donde esta asistencia',
                ],
                'restricted_to' => ['admin'],
                'restricted_response' => 'Esa sección del sidebar pertenece al panel del administrador. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "En el sidebar admin, los enlaces \"Instructorías\" (bajo Gestión) y \"Reportes\" / \"Asistencia\" (bajo Análisis) todavía NO tienen vista implementada: son placeholders y por eso no responden al hacer clic.\n\n"
                    ."Mientras tanto, para revisar instructorías y asistencia, el coordinador puede usar \"Instructorías\" en su panel y el instructor puede usar \"Asistencia\" en el suyo.",
                'suggestions' => [
                    '¿Cómo veo las notificaciones?',
                    '¿Cómo creo un coordinador?',
                    '¿Cómo elimino un instructor?',
                ],
            ],

            // ═══════════════════════════════════════════════════════════
            //  COORDINATOR — Grupos de clase
            // ═══════════════════════════════════════════════════════════

            [
                'id' => 'create_group',
                'keywords' => [
                    'crear grupo', 'nuevo grupo', 'crear clase', 'nuevo grupo de clase',
                    'agregar grupo', 'crear materia', 'registrar grupo',
                ],
                'restricted_to' => ['coordinator'],
                'restricted_response' => "Solo los coordinadores pueden crear grupos de clase. "
                    .'Si necesitas que se cree un grupo, pídele a tu coordinador. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para crear un grupo de clase (coordinador):\n\n"
                    ."1. Ve a \"Grupos de clase\" en el sidebar.\n"
                    ."2. Pulsa \"+ Nuevo grupo\" arriba a la derecha.\n"
                    ."3. Llena Materia, Docente, Ciclo, Modalidad (Presencial o En línea) y Horario.\n"
                    ."4. Si es Presencial, llena \"Aula física\" (ej. \"Aula 204 - Edificio A\"). Si es En línea, llena \"Enlace virtual\".\n"
                    ."5. Pulsa \"Guardar\".\n\n"
                    .'Verás "Grupo creado correctamente." Luego podes asignarle un instructor y cargar los estudiantes.',
                'suggestions' => [
                    '¿Cómo asigno un instructor al grupo?',
                    '¿Cómo importo estudiantes?',
                    '¿Cómo veo los estudiantes inscritos?',
                ],
            ],

            [
                'id' => 'edit_group',
                'keywords' => [
                    'editar grupo', 'modificar grupo', 'cambiar grupo', 'actualizar grupo',
                    'editar materia', 'cambiar horario grupo', 'cambiar modalidad',
                ],
                'restricted_to' => ['coordinator'],
                'restricted_response' => 'Solo los coordinadores pueden editar grupos. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para editar un grupo:\n\n"
                    ."1. Ve a \"Grupos de clase\".\n"
                    ."2. En la fila del grupo abre el menú \"Acciones\" y elige \"Editar grupo\".\n"
                    ."3. Cambia los campos (si cambias de Presencial a En línea, llena el enlace; si vas a Presencial, llena el aula).\n"
                    ."4. Pulsa \"Actualizar\".\n\n"
                    .'Verás "Grupo actualizado correctamente."',
                'suggestions' => [
                    '¿Cómo elimino un grupo?',
                    '¿Cómo asigno un instructor al grupo?',
                    '¿Cómo importo estudiantes?',
                ],
            ],

            [
                'id' => 'delete_group',
                'keywords' => [
                    'eliminar grupo', 'borrar grupo', 'quitar grupo', 'dar de baja grupo',
                    'no me deja eliminar grupo',
                ],
                'restricted_to' => ['coordinator'],
                'restricted_response' => 'Solo los coordinadores pueden eliminar grupos. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para eliminar un grupo:\n\n"
                    ."1. Ve a \"Grupos de clase\".\n"
                    ."2. En la fila abre el menú \"Acciones\" y elige \"Eliminar grupo\" (rojo).\n"
                    ."3. Confirma el modal \"¿Eliminar grupo?\" pulsando \"Sí, eliminar\".\n\n"
                    .'Importante: se borran las asignaciones de instructor y los estudiantes del grupo. La acción no se puede deshacer. Verás "Grupo eliminado correctamente."',
                'suggestions' => [
                    '¿Cómo creo un grupo?',
                    '¿Cómo edito un grupo?',
                    '¿Cómo asigno un instructor al grupo?',
                ],
            ],

            [
                'id' => 'assign_instructor_to_group',
                'keywords' => [
                    'asignar instructor', 'asignar tutor', 'asignar un instructor',
                    'asignar instructor a un grupo', 'asignar instructor a grupo',
                    'asignar grupo', 'asignar un grupo', 'cambiar instructor del grupo',
                    'cambiar instructor de grupo', 'agregar instructor al grupo',
                    'agregar instructor a grupo', 'poner instructor',
                    'desasignar instructor', 'quitar instructor', 'quitar instructor del grupo',
                    'quitar instructor de grupo', 'quitar tutor', 'reemplazar instructor',
                ],
                'restricted_to' => ['coordinator'],
                'restricted_response' => 'Solo los coordinadores pueden asignar instructores a grupos. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para asignar (o cambiar) un instructor a un grupo:\n\n"
                    ."1. Ve a \"Grupos de clase\".\n"
                    ."2. En la fila del grupo abre el menú \"Acciones\" y elige \"Asignar instructor\".\n"
                    ."3. En el modal elige el instructor de la lista (radio) y pulsa \"Confirmar asignación\".\n\n"
                    .'Verás "Instructor asignado al grupo." Si volves a abrir el modal, podes elegir otro instructor y reemplazar al anterior. Si no aparece nadie en la lista, primero crea un instructor en "Mis instructores".',
                'suggestions' => [
                    '¿Cómo creo un instructor?',
                    '¿Cómo veo los estudiantes inscritos?',
                    '¿Cómo veo las instructorías de un instructor?',
                ],
            ],

            [
                'id' => 'view_enrolled_students',
                'keywords' => [
                    'ver estudiantes', 'ver los estudiantes', 'lista de estudiantes',
                    'estudiantes del grupo', 'estudiantes inscritos', 'inscritos en el grupo',
                    'ver inscritos', 'quienes estan inscritos', 'alumnos del grupo',
                    'ver alumnos', 'lista de inscritos',
                ],
                'restricted_to' => ['coordinator', 'instructor'],
                'restricted_response' => 'Para ver estudiantes inscritos necesitas ser coordinador o instructor del grupo. ¿Querés que te ponga en contacto con un administrador?',
                'responses' => [
                    'coordinator' => "Para ver los estudiantes inscritos de un grupo (coordinador):\n\n"
                        ."1. Ve a \"Grupos de clase\".\n"
                        ."2. En la fila del grupo, pulsa el badge azul con el número de estudiantes (o abre el menú \"Acciones\" > \"Ver estudiantes\").\n\n"
                        .'Verás la lista con #, Carnet, Nombre completo y Correo. Si no hay nadie cargado, usa "Agregar estudiantes".',
                    'instructor' => "Para ver los estudiantes de tu grupo (instructor):\n\n"
                        ."1. Ve a \"Mis grupos\" en el sidebar.\n"
                        ."2. Pulsa la tarjeta del grupo que quieras revisar.\n\n"
                        .'Verás la lista de inscritos con carnet, nombre y correo.',
                ],
                'response' => "Para ver los estudiantes de un grupo entra a \"Grupos de clase\" (coordinador) o \"Mis grupos\" (instructor) y abre el grupo. Verás carnet, nombre y correo de cada inscrito.",
                'suggestions' => [
                    '¿Cómo importo estudiantes?',
                    '¿Cómo creo un grupo?',
                    '¿Cómo tomo asistencia con QR?',
                ],
            ],

            [
                'id' => 'import_students',
                'keywords' => [
                    'importar estudiantes', 'subir estudiantes', 'cargar estudiantes',
                    'agregar estudiantes', 'subir excel', 'subir csv', 'archivo excel',
                    'archivo csv', 'cargar lista', 'importar alumnos', 'cargar carnets',
                    'registrar los estudiantes', 'registrar estudiantes',
                ],
                'restricted_to' => ['coordinator'],
                'restricted_response' => 'Solo los coordinadores pueden importar estudiantes. Si quieres que se cargue una lista, pídele a tu coordinador. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para importar estudiantes a un grupo (coordinador):\n\n"
                    ."1. Ve a \"Grupos de clase\".\n"
                    ."2. En el menú \"Acciones\" del grupo elige \"Agregar estudiantes\".\n"
                    ."3. Arrastra (o selecciona) un archivo .xlsx, .xls o .csv con las columnas carnet, nombre completo, correo. Máximo 10 MB y 5000 filas.\n"
                    ."4. Revisa la vista previa: cada fila aparece como \"Válido\" o \"Error\" (carnet/correo vacío, correo inválido, correo ya registrado, carnet/correo duplicado).\n"
                    ."5. Pulsa \"Confirmar importación\".\n\n"
                    .'Verás "Se importaron N estudiante(s) al grupo {nombre}."',
                'suggestions' => [
                    '¿Mi archivo .xlsx no se sube, qué hago?',
                    '¿Cómo veo los estudiantes inscritos?',
                    '¿Cómo tomo asistencia con QR?',
                ],
            ],

            [
                'id' => 'import_students_problems',
                'keywords' => [
                    'no se sube el excel', 'no se sube el xlsx', 'no se sube xlsx',
                    'no se sube el archivo', 'archivo no se sube', 'xlsx no se sube',
                    'error al subir excel', 'error al subir xlsx', 'error al importar',
                    'no carga el archivo', 'no me lee el xlsx', 'no me lee el csv',
                    'sheetjs no carga', 'demasiadas filas', 'extension no permitida',
                    'formato no soportado', 'no se cargo el lector',
                ],
                'restricted_to' => ['coordinator'],
                'restricted_response' => 'La carga de estudiantes la hace el coordinador. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Si la carga de estudiantes falla, revisa estos puntos:\n\n"
                    ."1. Extensión: solo .xlsx, .xls o .csv. Si no, verás \"El archivo debe tener extensión .xlsx, .xls o .csv.\"\n"
                    ."2. Tamaño máximo 10 MB; máximo 5000 filas (\"El archivo tiene demasiadas filas (máximo 5000).\").\n"
                    ."3. .xlsx / .xls se leen en el navegador con SheetJS (CDN). Si no tienes internet, falla la carga del lector y aparece \"No se pudo cargar el lector de Excel...\". Solución: subí un .csv en su lugar.\n"
                    ."4. Columnas: deben aparecer carnet, nombre completo y correo (en ese orden si no hay encabezado).\n"
                    .'5. Si una fila quedó en "Error", lee el mensaje exacto ("carnet vacío", "correo inválido", "correo ya registrado", "carnet repetido en el archivo", etc.).',
                'suggestions' => [
                    '¿Cómo importo estudiantes?',
                    '¿Cómo veo los estudiantes inscritos?',
                    '¿Cómo creo un grupo?',
                ],
            ],

            // ═══════════════════════════════════════════════════════════
            //  COORDINATOR — Instructorías (lista por instructor)
            // ═══════════════════════════════════════════════════════════

            [
                'id' => 'view_instructorias',
                'keywords' => [
                    'ver instructorias', 'sesiones del instructor', 'historial del instructor',
                    'tutorias dadas', 'sesiones realizadas', 'instructorias por instructor',
                    'cuantas sesiones dio',
                ],
                'restricted_to' => ['coordinator'],
                'restricted_response' => 'El listado de instructorías por instructor vive en el panel del coordinador. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para ver las instructorías que ha dado un instructor (coordinador):\n\n"
                    ."1. Ve a \"Instructorías\" en el sidebar.\n"
                    ."2. Verás tarjetas por cada instructor con grupos, sesiones y asistencias.\n"
                    ."3. Pulsa la tarjeta del instructor.\n"
                    ."4. Se abre la tabla con Grupo, Fecha, Hora inicio, Hora fin, Duración, Asistentes y Estado (Abierta / Finalizada).\n\n"
                    .'Si una sesión aún no fue cerrada verás "En curso" en la hora fin.',
                'suggestions' => [
                    '¿Cómo exporto las instructorías a Excel?',
                    '¿Cómo asigno un instructor a un grupo?',
                    '¿Cómo creo un instructor?',
                ],
            ],

            [
                'id' => 'export_instructorias_excel',
                'keywords' => [
                    'exportar instructorias', 'descargar instructorias', 'excel instructorias',
                    'exportar sesiones instructor', 'reporte instructor excel',
                ],
                'restricted_to' => ['coordinator'],
                'restricted_response' => 'La exportación de instructorías por instructor la hace el coordinador. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para descargar el Excel de las instructorías de un instructor:\n\n"
                    ."1. Ve a \"Instructorías\" en el sidebar.\n"
                    ."2. Entra a la tarjeta del instructor.\n"
                    ."3. Pulsa el botón verde \"Exportar Excel\" arriba a la derecha.\n\n"
                    ."Se descarga el archivo instructorias-{nombre}-YYYY-MM-DD.xlsx con dos hojas:\n"
                    ."- \"Sesiones\": Fecha, Grupo, Hora inicio, Hora fin, Duración, Asistentes y Estado.\n"
                    ."- \"Resumen\": sesiones totales, asistencias totales, promedio por sesión y grupos atendidos.\n\n"
                    .'El botón solo aparece si el instructor tiene al menos una sesión realizada.',
                'suggestions' => [
                    '¿Cómo veo las instructorías?',
                    '¿Cómo exporta el instructor su asistencia?',
                    '¿Cómo finalizo una sesión QR?',
                ],
            ],

            // ═══════════════════════════════════════════════════════════
            //  INSTRUCTOR — Mis grupos / QR / asistencia
            // ═══════════════════════════════════════════════════════════

            [
                'id' => 'my_groups',
                'keywords' => [
                    'mis grupos', 'mis clases', 'que grupos tengo', 'grupos asignados',
                    'mis instructorias', 'mis tutorias', 'donde veo mis grupos',
                ],
                'restricted_to' => ['instructor'],
                'restricted_response' => 'Esa vista pertenece al panel del instructor. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para ver tus grupos (instructor):\n\n"
                    ."1. Ve a \"Mis grupos\" en el sidebar.\n"
                    ."2. Verás una tarjeta por cada grupo asignado con materia, profesor, ciclo, horario, modalidad y cantidad de estudiantes.\n"
                    ."3. Pulsa la tarjeta para ver el detalle del grupo y la lista de estudiantes inscritos.\n\n"
                    .'Si no tenes nada asignado verás "No tienes grupos asignados todavía." — pedile a tu coordinador que te asigne un grupo.',
                'suggestions' => [
                    '¿Cómo inicio una sesión con QR?',
                    '¿Cómo veo la asistencia?',
                    '¿Cómo exporto la asistencia?',
                ],
            ],

            [
                'id' => 'start_qr_session',
                'keywords' => [
                    'iniciar sesion qr', 'iniciar una sesion qr', 'iniciar una sesion con qr',
                    'iniciar la sesion qr', 'iniciar qr', 'iniciar el qr',
                    'iniciar otra sesion', 'iniciar nueva sesion', 'iniciar nueva sesion qr',
                    'sesion con qr', 'sesion nueva qr',
                    'generar qr', 'crear qr', 'tomar asistencia', 'tomar la asistencia',
                    'iniciar tutoria', 'iniciar clase', 'pasar lista',
                    'sesion qr', 'codigo qr', 'asistencia qr', 'abrir qr',
                ],
                'restricted_to' => ['instructor'],
                'restricted_response' => "Solo el instructor de un grupo puede iniciar la sesión con QR. "
                    ."Si necesitas tomar asistencia y no sos instructor, contacta al instructor titular o al coordinador. "
                    .'¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para iniciar una sesión con QR (instructor):\n\n"
                    ."1. Ve a \"Iniciar sesión\" en el sidebar.\n"
                    ."2. Revisa que el grupo, horario y modalidad sean correctos. Confirma fecha y hora de inicio.\n"
                    ."3. Pulsa \"Generar QR e iniciar\". El sistema crea la sesión y muestra el QR, el enlace clicable y el código (ej. PROGRAMA-2026-004).\n"
                    ."4. Los estudiantes escanean el QR (o abren el enlace) y escriben su carnet para marcar asistencia.\n"
                    ."5. El contador \"Asistencias en sesión\" sube en tiempo real cuando recargas.\n\n"
                    ."Si ya tenías una sesión abierta verás el error \"Ya tienes una sesión activa. Finalízala antes de iniciar otra.\"\n"
                    .'Si no tenes grupo asignado, el botón queda deshabilitado: "No hay instructoría asignada. Contacta a tu coordinador."',
                'suggestions' => [
                    '¿Cómo finalizo la sesión QR?',
                    '¿Cómo veo la asistencia de mis estudiantes?',
                    '¿Y si tengo más de un grupo asignado?',
                ],
            ],

            [
                'id' => 'multiple_groups_session',
                'keywords' => [
                    'tengo varios grupos', 'mas de un grupo', 'varios grupos asignados',
                    'que grupo se inicia', 'cual grupo agarra', 'tengo 2 grupos',
                ],
                'restricted_to' => ['instructor'],
                'restricted_response' => 'Esa duda aplica al instructor. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Si tenes varias instructorías asignadas, la vista \"Iniciar sesión\" elige automáticamente la primera con estado \"Activo\" (o la primera asignada si no hay columna de estado).\n\n"
                    .'Por ahora la vista NO tiene selector manual de grupo: si quieres tomar asistencia de un grupo distinto, pedi al coordinador que marque ese grupo como Activo. Mientras tanto, podes consultar los demás grupos en "Mis grupos".',
                'suggestions' => [
                    '¿Cómo inicio una sesión QR?',
                    '¿Cómo veo mis grupos?',
                    '¿Cómo solicito soporte?',
                ],
            ],

            [
                'id' => 'end_qr_session',
                'keywords' => [
                    // Mantenemos SOLO frases con "qr", "tutoria", "clase" o
                    // "asistencia" para no chocar con el intent logout
                    // ("cerrar sesion" suele significar cerrar la sesión del
                    // usuario, no la del QR).
                    'finalizar sesion qr', 'finalizar la sesion qr',
                    'terminar sesion qr', 'cerrar sesion qr',
                    'cerrar qr', 'cerrar el qr', 'detener qr', 'detener el qr',
                    'apagar qr', 'cerrar clase', 'finalizar clase',
                    'finalizar tutoria', 'terminar tutoria', 'detener asistencia',
                    'finalizar asistencia',
                ],
                'restricted_to' => ['instructor'],
                'restricted_response' => 'Solo el instructor que abrió la sesión puede finalizarla. ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para finalizar la sesión con QR:\n\n"
                    ."1. En \"Iniciar sesión\", con la sesión activa, pulsa el botón rojo \"Finalizar sesión\".\n"
                    ."2. Confirma el diálogo \"¿Finalizar la sesión? Ya no se podrán registrar más asistencias.\"\n"
                    ."3. El sistema cierra la sesión: ningún estudiante más puede marcar y el QR queda inválido (los que abran el enlace verán \"Esta sesión no está disponible o ya fue finalizada.\").\n\n"
                    .'Las asistencias ya registradas se conservan y podes revisarlas o exportarlas desde "Asistencia".',
                'suggestions' => [
                    '¿Cómo veo la asistencia?',
                    '¿Cómo exporto la asistencia a Excel?',
                    '¿Cómo inicio otra sesión?',
                ],
            ],

            [
                'id' => 'view_attendance_matrix',
                'keywords' => [
                    'ver asistencia', 'ver la asistencia', 'ver mi asistencia',
                    'mi asistencia', 'matriz de asistencia', 'quien asistio',
                    'historial de asistencia', 'reporte de asistencia',
                    'asistencia de mis estudiantes', 'asistencia por sesion',
                ],
                'restricted_to' => ['instructor'],
                'restricted_response' => 'La matriz de asistencia detallada está en el panel del instructor titular. El coordinador puede ver el resumen desde "Instructorías". ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para ver la matriz de asistencia (instructor):\n\n"
                    ."1. Ve a \"Asistencia\" en el sidebar.\n"
                    ."2. Elige la tarjeta de la instructoría que quieras revisar.\n"
                    ."3. Verás la matriz: filas = estudiantes inscritos, columnas = sesiones (fecha + hora). Cada celda marca con tilde verde si asistió o \"—\" si no.\n"
                    ."4. La columna \"Total\" muestra N/M y el pie de tabla muestra \"Asistentes por sesión\".\n\n"
                    .'Si la instructoría aún no tiene sesiones verás "Esta instructoría aún no tiene sesiones registradas." y un botón para iniciar una.',
                'suggestions' => [
                    '¿Cómo exporto la asistencia a Excel?',
                    '¿Cómo inicio una sesión QR?',
                    '¿Cómo veo mis grupos?',
                ],
            ],

            [
                'id' => 'export_attendance_excel',
                'keywords' => [
                    'exportar asistencia', 'exportar mi asistencia',
                    'exportar la asistencia', 'exportar asistencia a excel',
                    'exportar asistencia excel', 'exportar mi asistencia a excel',
                    'descargar asistencia', 'descargar mi asistencia',
                    'excel de asistencia', 'excel asistencia', 'xlsx asistencia',
                    'bajar reporte asistencia', 'descargar matriz', 'bajar asistencia',
                ],
                'restricted_to' => ['instructor'],
                'restricted_response' => 'La exportación de la matriz la hace el instructor titular. Si sos coordinador, podes exportar el listado de sesiones por instructor desde "Instructorías". ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para descargar la asistencia en Excel (instructor):\n\n"
                    ."1. Ve a \"Asistencia\" en el sidebar.\n"
                    ."2. Entra a la instructoría deseada.\n"
                    ."3. Pulsa el botón verde \"Exportar Excel\" arriba a la derecha.\n\n"
                    ."Se descarga asistencia-{grupo}-YYYY-MM-DD.xlsx con dos hojas:\n"
                    ."- \"Matriz\": estudiantes × sesiones con tildes verdes para asistencia.\n"
                    ."- \"Resumen\": sesiones realizadas, asistencias totales, promedio y la sesión con más / menos asistentes.\n\n"
                    .'El botón solo aparece si hay al menos una sesión Y al menos un estudiante inscrito.',
                'suggestions' => [
                    '¿Cómo veo la asistencia?',
                    '¿Cómo inicio una sesión QR?',
                    '¿Cómo importo estudiantes?',
                ],
            ],

            [
                'id' => 'instructor_dashboard_mock',
                'keywords' => [
                    'lucia', 'quien es lucia', 'programacion i', 'mi dashboard',
                    'dashboard instructor', 'datos del dashboard incorrectos',
                    'porque veo lucia', 'datos falsos dashboard',
                ],
                'response' => "El dashboard del instructor (la pantalla con \"Buen día, Lucía\", \"Programación I\", 28 estudiantes, etc.) hoy es una pantalla de DEMO con datos fijos: aún no consume tu información real.\n\n"
                    ."Para datos reales, usa los menús del sidebar:\n"
                    ."- \"Mis grupos\": tus grupos asignados reales.\n"
                    ."- \"Asistencia\": la matriz real de quienes han marcado.\n"
                    .'- "Iniciar sesión": para abrir el QR de tu sesión actual.',
                'suggestions' => [
                    '¿Cómo veo mis grupos reales?',
                    '¿Cómo inicio una sesión QR?',
                    '¿Cómo veo mi asistencia real?',
                ],
            ],

            // ═══════════════════════════════════════════════════════════
            //  COMPARTIDO — Login / logout / perfil / contraseña
            // ═══════════════════════════════════════════════════════════

            [
                'id' => 'login_problem',
                'keywords' => [
                    'no puedo iniciar sesion', 'no puedo entrar', 'no me deja entrar',
                    'olvide mi contrasena', 'olvide la contrasena', 'recuperar contrasena',
                    'reset contrasena', 'error de login', 'mi contrasena no funciona',
                    'no puedo loguearme', 'credenciales no coinciden',
                ],
                'response' => "Tranquilo, te ayudo. Revisemos paso a paso:\n\n"
                    ."1. Verificá que el correo esté sin espacios al inicio o al final.\n"
                    ."2. La contraseña distingue mayúsculas y minúsculas.\n"
                    ."3. Tu cuenta tiene que existir (la crea un admin o un coordinador; no hay auto-registro).\n"
                    ."4. Si te aparece \"El correo o la contraseña están equivocados.\", revisá ambos campos.\n"
                    ."5. Si te aparece \"Tu cuenta no tiene un rol válido asignado.\", un administrador debe asignarte un rol.\n\n"
                    .'Por ahora no hay auto-reseteo de contraseña. Si nada de esto funcionó, ¿querés que te ponga en contacto con un administrador?',
                'suggestions' => [
                    '¿Cómo cambio mi contraseña?',
                    '¿Cómo edito mi perfil?',
                    '¿Cómo solicito soporte?',
                ],
            ],

            [
                'id' => 'logout',
                'keywords' => [
                    'cerrar sesion', 'salir', 'logout', 'desconectar', 'salirme',
                    'cerrar mi sesion', 'log out',
                ],
                'response' => "Para cerrar sesión:\n\n"
                    ."1. Pulsa tu avatar arriba a la derecha (las iniciales en el círculo azul).\n"
                    ."2. En el menú que se abre elige \"Cerrar sesión\".\n\n"
                    .'Te lleva a la pantalla de login. La próxima vez tendrás que ingresar correo y contraseña.',
                'suggestions' => [
                    '¿Cómo cambio mi contraseña?',
                    '¿Cómo edito mi perfil?',
                ],
            ],

            [
                'id' => 'change_password',
                'keywords' => [
                    'cambiar contrasena', 'cambiar mi contrasena', 'cambiar tu contrasena',
                    'cambiar password', 'actualizar contrasena', 'actualizar mi contrasena',
                    'nueva contrasena', 'modificar contrasena', 'cambiar mi clave',
                    'cambiar la contrasena', 'resetear contrasena', 'resetear mi contrasena',
                    'restablecer contrasena', 'olvide mi contrasena', 'no recuerdo mi contrasena',
                ],
                // Solo el admin puede gestionar/resetear contraseñas. Instructores y
                // coordinadores ya no tienen vista "Mi perfil" en el sidebar, así
                // que dependen del administrador.
                'restricted_to' => ['admin'],
                'restricted_response' => "Las contraseñas las gestiona el administrador. Como instructor o coordinador no tenés acceso a la vista \"Mi perfil\", "
                    ."así que si querés cambiar o restablecer tu contraseña hay que pedírselo al admin.\n\n"
                    .'¿Querés que te ponga en contacto con un administrador?',
                'response' => "Para cambiar contraseñas (administrador):\n\n"
                    ."• Tu propia contraseña: pulsa tu avatar arriba a la derecha y elige \"Mi perfil\". En el bloque \"Cambiar contraseña\" llena la actual, la nueva (mín. 8 caracteres) y la confirmación. Pulsa \"Actualizar contraseña\".\n\n"
                    ."• La contraseña de un coordinador o instructor: ve a \"Coordinadores\" o \"Instructores\", pulsa el ícono de editar y escribe la nueva contraseña en el campo correspondiente. Al guardar, queda activa de inmediato.",
                'suggestions' => [
                    '¿Cómo edito mi perfil?',
                    '¿Cómo edito un coordinador?',
                    '¿Cómo edito un instructor?',
                ],
                'suggestions_by_role' => [
                    'coordinator' => [
                        '¿Cómo contacto al administrador?',
                        '¿Qué hago si no puedo iniciar sesión?',
                    ],
                    'instructor' => [
                        '¿Cómo contacto al administrador?',
                        '¿Qué hago si no puedo iniciar sesión?',
                    ],
                ],
            ],

            [
                'id' => 'edit_profile',
                'keywords' => [
                    'mi perfil', 'editar mi perfil', 'editar perfil', 'datos personales',
                    'cambiar nombre', 'cambiar mi nombre', 'editar nombre', 'editar mi nombre',
                    'cambiar mi correo', 'cambiar correo', 'cambiar mi correo electronico',
                    'cambiar mi nombre o correo', 'actualizar mis datos',
                    'mi cuenta',
                ],
                'response' => "Para editar tus datos:\n\n"
                    ."1. Pulsa tu avatar arriba a la derecha → \"Mi perfil\".\n"
                    ."2. En \"Información personal\" podes cambiar tu nombre completo y guardar.\n"
                    ."3. El correo aparece deshabilitado: NO se puede cambiar desde aquí. Si necesitas cambiarlo, contacta al administrador.\n\n"
                    .'Cuando guardas el nombre verás "Tu nombre se actualizó correctamente."',
                'suggestions' => [
                    '¿Cómo cambio mi contraseña?',
                    '¿Por qué veo el sidebar del admin en mi perfil?',
                    '¿Cómo cierro sesión?',
                ],
            ],

            [
                'id' => 'profile_layout_bug',
                'keywords' => [
                    'sidebar del admin', 'veo el menu del admin', 'cambia el sidebar',
                    'porque veo coordinadores', 'menu del admin en mi perfil',
                    'mi perfil se ve raro',
                ],
                'response' => "Es un detalle conocido: la página \"Mi perfil\" siempre se renderiza con el sidebar del administrador, incluso si sos coordinador o instructor. Es un detalle visual; los formularios para editar tu nombre y contraseña funcionan igual.\n\n"
                    .'Para volver a tu panel normal, usa el enlace "Inicio" del breadcrumb o pulsa el logo arriba a la izquierda.',
                'suggestions' => [
                    '¿Cómo cambio mi contraseña?',
                    '¿Cómo edito mi nombre?',
                    '¿Cómo cierro sesión?',
                ],
            ],

            [
                'id' => 'modality_presencial_vs_online',
                'keywords' => [
                    'modalidad', 'presencial', 'en linea', 'virtual', 'modalidad del grupo',
                    'que modalidades hay', 'aula vs enlace', 'modalidad presencial o en linea',
                ],
                'response' => "Cada grupo de clase tiene una modalidad:\n\n"
                    ."- Presencial: la clase es en aula física. Se guarda el aula (\"Aula 204 - Edificio A\").\n"
                    ."- En línea: la clase es remota. Se guarda un enlace (ej. https://meet.google.com/abc-xyz) visible para el instructor al iniciar la sesión.\n\n"
                    .'La modalidad se elige al crear o editar el grupo desde "Grupos de clase". El instructor ve esa info en "Iniciar sesión" y en "Mis grupos".',
                'suggestions' => [
                    '¿Cómo creo un grupo de clase?',
                    '¿Cómo edito un grupo?',
                    '¿Cómo inicio una sesión QR?',
                ],
            ],

            // ═══════════════════════════════════════════════════════════
            //  ASISTENCIA QR PÚBLICA (estudiante)
            // ═══════════════════════════════════════════════════════════

            [
                'id' => 'student_how_to_mark',
                'keywords' => [
                    'como marco asistencia', 'como registro asistencia', 'soy estudiante',
                    'soy alumno', 'escanee el qr', 'me dieron un qr', 'donde pongo mi carnet',
                    'marcar mi asistencia', 'registrar mi asistencia', 'estudiante asistencia',
                ],
                'response' => "Para marcar tu asistencia como estudiante:\n\n"
                    ."1. Escanea el código QR que muestra tu instructor (o abre el enlace que aparece debajo del código).\n"
                    ."2. Se abre la página \"Registrar asistencia\" con el nombre del grupo y el código de sesión.\n"
                    ."3. Escribe tu número de carnet (ej. 20210001) y pulsa \"Confirmar asistencia\".\n"
                    ."4. Si todo está bien verás un cuadro verde \"Asistencia registrada correctamente\" con tu nombre.\n\n"
                    .'No necesitas iniciar sesión. Solo se acepta carnet si tu nombre está cargado en la lista del grupo.',
                'suggestions' => [
                    '¿Por qué dice "sesión cerrada"?',
                    '¿Por qué dice que ya marqué asistencia?',
                    '¿Y si mi carnet no está en la lista?',
                ],
            ],

            [
                'id' => 'student_session_closed',
                'keywords' => [
                    'sesion cerrada', 'sesion esta cerrada', 'sesion no disponible',
                    'qr no funciona', 'token invalido', 'sesion ya fue finalizada',
                    'enlace no abre', 'qr expirado',
                ],
                'response' => "Si al abrir el QR ves la pantalla \"Sesión cerrada\" con el texto \"Esta sesión no está disponible o ya fue finalizada.\":\n\n"
                    ."1. El instructor ya pulsó \"Finalizar sesión\" y el QR dejó de aceptar registros.\n"
                    ."2. O el enlace que estás usando corresponde a otra sesión que ya terminó.\n\n"
                    .'Pídele al instructor que abra una nueva sesión y comparta el nuevo QR. Las asistencias antiguas se quedan guardadas, no se pierden.',
                'suggestions' => [
                    '¿Cómo marco asistencia?',
                    '¿Y si me dice "ya marcaste"?',
                    '¿Mi carnet no está en la lista?',
                ],
            ],

            [
                'id' => 'student_already_marked',
                'keywords' => [
                    'ya marque asistencia', 'ya marque', 'ya marcaste', 'ya me marcaste',
                    'ya se registro tu asistencia', 'ya esta registrada',
                    'aparece en azul', 'me sale ya registrado', 'duplicado de asistencia',
                ],
                'response' => "Si después de poner tu carnet ves un cuadro azul \"Ya se registró tu asistencia\" con tu nombre, significa que ese carnet ya tiene una asistencia guardada en esta misma sesión.\n\n"
                    ."No pasa nada: solo se cuenta UNA vez por sesión. No necesitas volver a marcar.\n\n"
                    .'Si crees que es un error (no fuiste vos quien marcó), avisa al instructor o coordinador.',
                'suggestions' => [
                    '¿Cómo marco asistencia?',
                    '¿Cómo solicito soporte?',
                    '¿Y si la sesión está cerrada?',
                ],
            ],

            [
                'id' => 'student_carnet_not_in_list',
                'keywords' => [
                    'mi carnet no esta', 'no estoy inscrito', 'no aparezco en la lista',
                    'no estas inscrito en esta clase', 'verifica el numero',
                    'mi carnet no funciona', 'no me reconoce el carnet',
                ],
                'response' => "Si al confirmar tu carnet ves en rojo \"No estás inscrito en esta clase con ese carnet. Verifica el número o contacta a tu coordinador.\":\n\n"
                    ."1. Revisa que escribiste tu carnet exactamente como está en el sistema (sin espacios extra, sin guiones).\n"
                    ."2. Si está bien escrito y aún así falla, no estás cargado en la lista del grupo. El coordinador debe importar tu carnet desde \"Agregar estudiantes\".\n"
                    ."3. Contacta a tu coordinador (no al instructor): el coordinador controla la lista de inscritos del grupo.\n\n"
                    .'¿Querés que te ponga en contacto con un administrador?',
                'suggestions' => [
                    '¿Cómo marco asistencia?',
                    '¿Cómo solicito soporte?',
                    '¿Cómo importan los estudiantes?',
                ],
            ],

            // ═══════════════════════════════════════════════════════════
            //  DUDAS GENÉRICAS DE USO (errores comunes y "no veo X")
            // ═══════════════════════════════════════════════════════════

            [
                'id' => 'permissions_error',
                'keywords' => [
                    'error de permisos', 'no tengo permiso', 'permiso denegado',
                    'forbidden', 'no autorizado', 'unauthorized', '403',
                    'no tienes acceso', 'no puedes ver esto', 'que significa error de permisos',
                ],
                'response' => "Un \"error de permisos\" o \"no autorizado\" aparece cuando intentas entrar a una pantalla o acción que no es para tu rol.\n\n"
                    ."Ejemplos comunes:\n"
                    ."- Un instructor entrando a /admin/* (solo admin).\n"
                    ."- Un coordinador entrando a /admin/coordinadores (solo admin).\n"
                    ."- Una sesión expirada (el sistema te devuelve al login).\n\n"
                    ."Qué hacer:\n"
                    ."1. Volvé al panel principal con el logo arriba a la izquierda.\n"
                    ."2. Si seguís viendo el error, cerrá sesión e iniciá de nuevo.\n"
                    ."3. Si necesitas acceso a esa sección, pedile al administrador que revise tu rol.\n\n"
                    .'¿Querés que te ponga en contacto con un administrador?',
                'suggestions' => [
                    '¿Qué roles existen?',
                    '¿Cómo cierro sesión?',
                    '¿Cómo cambio mi contraseña?',
                ],
            ],

            [
                'id' => 'cant_see_my_groups',
                'keywords' => [
                    'no veo mis grupos', 'no me aparecen mis grupos', 'donde estan mis grupos',
                    'mis grupos vacios', 'no tengo grupos', 'no aparecen mis instructorias',
                    'no me asignaron grupo', 'porque no veo grupos',
                ],
                'restricted_to' => ['instructor'],
                'restricted_response' => 'Esta duda aplica al instructor. Si sos coordinador y un instructor no ve sus grupos, revisá que se los hayas asignado desde "Grupos de clase". ¿Querés que te ponga en contacto con un administrador?',
                'response' => "Si no ves tus grupos en \"Mis grupos\", normalmente es porque tu coordinador aún no te asignó ninguno. Probá esto:\n\n"
                    ."1. Refrescá la página (F5).\n"
                    ."2. Cerrá sesión y volvé a entrar para refrescar tu cuenta.\n"
                    ."3. Si seguís sin ver nada y verás \"No tienes grupos asignados todavía.\", pedile a tu coordinador que te asigne un grupo desde \"Grupos de clase\" > \"Acciones\" > \"Asignar instructor\".\n\n"
                    .'Si después de eso seguís sin ver el grupo, ¿querés que te ponga en contacto con un administrador?',
                'suggestions' => [
                    '¿Cómo asigna un grupo el coordinador?',
                    '¿Cómo inicio una sesión QR?',
                    '¿Cómo solicito soporte?',
                ],
            ],

            [
                'id' => 'generate_reports',
                'keywords' => [
                    'generar reportes', 'como generar reportes', 'crear reporte',
                    'reportes', 'reporte de asistencia general', 'reporte general',
                    'estadisticas', 'informe', 'reporteria',
                ],
                'responses' => [
                    'admin' => "Hoy el panel admin tiene un enlace \"Reportes\" en el sidebar (bajo Análisis), pero todavía es un placeholder sin vista implementada. Mientras se construye, podes:\n\n"
                        ."- Pedirle al coordinador que exporte el Excel de instructorías de cada instructor (\"Instructorías\" > tarjeta del instructor > \"Exportar Excel\").\n"
                        ."- Pedirle al instructor que exporte la matriz de asistencia de su grupo (\"Asistencia\" > entrar a la instructoría > \"Exportar Excel\").\n\n"
                        ."¿Te ayudo con algo más?",
                    'coordinator' => "Para generar un reporte de un instructor (coordinador):\n\n"
                        ."1. Entra a \"Instructorías\" en el sidebar.\n"
                        ."2. Pulsa la tarjeta del instructor.\n"
                        ."3. Pulsa el botón verde \"Exportar Excel\" arriba a la derecha.\n\n"
                        .'Se descarga un .xlsx con dos hojas: "Sesiones" (detalle por sesión) y "Resumen" (totales y promedios). ¿Querés que te explique qué incluye cada hoja?',
                    'instructor' => "Para generar un reporte de asistencia de tu grupo (instructor):\n\n"
                        ."1. Entra a \"Asistencia\" en el sidebar.\n"
                        ."2. Abre la instructoría.\n"
                        ."3. Pulsa el botón verde \"Exportar Excel\" arriba a la derecha.\n\n"
                        ."Bajás un .xlsx con la matriz estudiantes × sesiones más una hoja resumen con totales y promedios. ¿Te ayudo con algo más?",
                ],
                'response' => "En Instructor Hub los reportes se generan exportando a Excel desde dos lugares:\n\n"
                    ."- \"Asistencia\" (instructor) → botón verde \"Exportar Excel\" en la matriz.\n"
                    ."- \"Instructorías\" (coordinador) → botón verde \"Exportar Excel\" en la tarjeta del instructor.\n\n"
                    .'¿Querés que te explique paso a paso uno de los dos?',
                'suggestions' => [
                    '¿Cómo exporto la asistencia a Excel?',
                    '¿Cómo exporto las instructorías?',
                    '¿Cómo veo la matriz de asistencia?',
                ],
            ],

            [
                'id' => 'create_users_general',
                'keywords' => [
                    'crear usuarios', 'crear usuario', 'nuevo usuario', 'agregar usuario',
                    'registrar usuario', 'dar de alta usuario',
                    'crear cuenta', 'crear una cuenta', 'nueva cuenta', 'necesito una cuenta',
                    'cuenta nueva', 'abrir cuenta',
                ],
                'response' => "En Instructor Hub no hay un menú genérico de \"Usuarios\": las cuentas se crean según el rol que necesitas.\n\n"
                    ."- Coordinador → lo crea el administrador desde \"Coordinadores\".\n"
                    ."- Instructor → lo crea un administrador o un coordinador desde \"Instructores\" / \"Mis instructores\".\n"
                    ."- Estudiantes → no tienen cuenta, solo se importan al grupo desde \"Agregar estudiantes\".\n\n"
                    ."¿Cuál de los tres querés crear?",
                'suggestions' => [
                    '¿Cómo creo un coordinador?',
                    '¿Cómo creo un instructor?',
                    '¿Cómo importo estudiantes?',
                ],
            ],

            // ═══════════════════════════════════════════════════════════
            //  EVALUACIONES (módulo completo: self / coordinator / student / teacher / admin)
            // ═══════════════════════════════════════════════════════════

            [
                'id' => 'evaluations_overview',
                'keywords' => [
                    'evaluacion', 'evaluaciones', 'modulo de evaluaciones',
                    'sobre evaluaciones', 'que son las evaluaciones',
                    'como funciona evaluaciones', 'como funcionan las evaluaciones',
                    'subir notas', 'poner notas', 'calificaciones',
                    'modulo de notas', 'examen', 'examenes',
                ],
                'response' => "Instructor Hub tiene un módulo de evaluaciones para medir el desempeño del instructor.\n\n"
                    ."Hay 4 tipos:\n"
                    ."- Autoevaluación (la hace el propio instructor).\n"
                    ."- Coordinador (la hace su coordinador encargado).\n"
                    ."- Estudiantes (las completan los estudiantes y se suben por Excel).\n"
                    ."- Docente titular (la completa el catedrático y se sube por Excel).\n\n"
                    ."Requisito clave: la instructoría debe estar en estado \"Finalizado\". Mientras esté \"Activo\" las evaluaciones siguen bloqueadas.\n\n"
                    ."¿Sobre qué parte querés saber más?",
                'suggestions' => [
                    '¿Cómo envío mi autoevaluación?',
                    '¿Cómo evalúo a un instructor?',
                    '¿Cómo importo evaluaciones de estudiantes?',
                    '¿Cómo veo todas las evaluaciones (admin)?',
                ],
            ],

            [
                'id' => 'evaluations_finalize_required',
                'keywords' => [
                    'no aparece evaluacion', 'no aparece la evaluacion',
                    'evaluacion bloqueada', 'no puedo evaluar', 'no me deja evaluar',
                    'porque no puedo evaluar', 'donde se habilita la evaluacion',
                    'finalizar instructoria', 'reactivar instructoria',
                    'estado activo finalizado', 'cambiar estado instructoria',
                ],
                'response' => "Las evaluaciones SOLO se habilitan cuando la instructoría está \"Finalizado\".\n\n"
                    ."Lo hace el coordinador desde:\n"
                    ."Coordinador → Instructorías → entrar al instructor → botón \"Finalizar instructoría\".\n\n"
                    ."Mientras esté \"Activo\":\n"
                    ."- Las opciones de evaluar no aparecen.\n"
                    ."- El instructor PUEDE seguir generando QR e iniciar sesiones.\n\n"
                    ."Si se finalizó por error, el coordinador puede pulsar \"Reactivar\" en la misma vista. Al reactivarla, el instructor puede volver a generar QR.",
                'suggestions' => [
                    '¿Cómo finalizo una instructoría?',
                    '¿Cómo envío mi autoevaluación?',
                    '¿Cómo evalúo a un instructor?',
                ],
            ],

            [
                'id' => 'evaluations_self',
                'keywords' => [
                    'autoevaluacion', 'auto evaluacion', 'auto-evaluacion',
                    'enviar autoevaluacion', 'completar autoevaluacion',
                    'mi autoevaluacion', 'evaluarme', 'evaluacion propia',
                    'autoevaluarme', 'autoevaluacion del instructor',
                ],
                'restricted_to' => ['instructor'],
                'restricted_response' => 'La autoevaluación la hace cada instructor desde su panel. Si querés ayuda con el flujo igual, te lo puedo explicar.',
                'response' => "Para enviar tu autoevaluación:\n\n"
                    ."1. Entra a Evaluaciones desde tu menú lateral.\n"
                    ."2. Verás tus instructorías FINALIZADAS. Si la tuya aparece como \"Pendiente\", pulsa \"Realizar autoevaluación\".\n"
                    ."3. El formulario tiene preguntas con escala 1 a 5 + algunas preguntas de texto.\n"
                    ."4. Pulsa \"Enviar autoevaluación\".\n\n"
                    ."Cuando la envíes, tu coordinador recibe una notificación automática en su burbuja para revisarla.\n\n"
                    ."Si ya la enviaste, podés volver a entrar y editarla (queda precargada con lo que mandaste).",
                'suggestions' => [
                    '¿Por qué no me aparece la evaluación?',
                    '¿Cómo veo el estado de mi autoevaluación?',
                    '¿Olvidé mi contraseña?',
                ],
            ],

            [
                'id' => 'evaluations_coordinator',
                'keywords' => [
                    'evaluar instructor', 'evaluar a mi instructor', 'evaluar tutor',
                    'calificar instructor', 'evaluacion del coordinador',
                    'como evaluo a un instructor', 'evaluar a un instructor',
                    'completar evaluacion del coordinador',
                ],
                'restricted_to' => ['coordinator'],
                'restricted_response' => 'La evaluación de coordinador la hace el coordinador encargado del instructor desde su panel.',
                'response' => "Para evaluar a un instructor que tengas a cargo:\n\n"
                    ."1. Ve a Evaluaciones desde tu menú lateral.\n"
                    ."2. Verás la lista de instructorías FINALIZADAS de tus instructores.\n"
                    ."3. Pulsa \"Evaluar\" en la fila del instructor que quieras.\n"
                    ."4. Completa las preguntas (1 a 5) y, si hay, los comentarios.\n"
                    ."5. Pulsa \"Guardar evaluación\".\n\n"
                    ."Notas importantes:\n"
                    ."- Solo ves a TUS instructores (los que tu cuenta creó o el admin te asignó).\n"
                    ."- Si la instructoría aún está \"Activo\" no aparecerá el botón \"Evaluar\". Primero finalízala desde Instructorías.\n"
                    ."- Tu evaluación reemplaza la anterior si la editas.",
                'suggestions' => [
                    '¿Cómo finalizo una instructoría?',
                    '¿Cómo importo evaluaciones de estudiantes?',
                    '¿Cómo importo la evaluación del docente?',
                ],
            ],

            [
                'id' => 'evaluations_import_students',
                'keywords' => [
                    'importar evaluaciones estudiantes', 'importar evaluacion estudiantes',
                    'subir evaluaciones estudiantes', 'evaluacion de estudiantes',
                    'evaluaciones de los estudiantes', 'cargar evaluaciones de estudiantes',
                    'plantilla evaluacion estudiantes', 'plantilla estudiantes evaluacion',
                    'excel evaluacion estudiantes', 'subir excel estudiantes',
                ],
                'restricted_to' => ['coordinator'],
                'restricted_response' => 'La importación de evaluaciones de estudiantes la hace el coordinador desde su panel.',
                'response' => "Para subir las evaluaciones que llenaron los estudiantes:\n\n"
                    ."1. Ve a Evaluaciones → entra a la instructoría.\n"
                    ."2. En la card del instructor verás el chip \"Estudiantes (n)\". Pulsalo.\n"
                    ."3. Paso 1: pulsa \"Descarga el formulario\" para bajar el .xlsx con un encabezado por pregunta. Pasalo a tus estudiantes o pegá las respuestas que ya tengas (una fila por estudiante).\n"
                    ."4. Paso 2: subí el archivo lleno y pulsa \"Importar\".\n\n"
                    ."Reglas:\n"
                    ."- Cada fila se guarda como una evaluación independiente con source = csv_import.\n"
                    ."- Si una fila tiene valores fuera de rango (no 1-10), se ajusta al rango y se reporta.\n"
                    ."- Podés importar el mismo tipo varias veces (no reemplaza, suma).",
                'suggestions' => [
                    '¿Cómo importo la evaluación del docente?',
                    '¿Cómo veo todas las evaluaciones (admin)?',
                    '¿Cómo evalúo a un instructor?',
                ],
            ],

            [
                'id' => 'evaluations_import_teacher',
                'keywords' => [
                    'importar evaluacion docente', 'importar evaluacion del docente',
                    'evaluacion del docente titular', 'subir evaluacion docente',
                    'cargar evaluacion docente', 'evaluacion catedratico',
                    'evaluacion del catedratico', 'evaluacion profesor titular',
                    'plantilla evaluacion docente', 'excel evaluacion docente',
                ],
                'restricted_to' => ['coordinator'],
                'restricted_response' => 'La importación de la evaluación del docente la hace el coordinador desde su panel.',
                'response' => "Para subir la evaluación que llenó el docente titular del curso:\n\n"
                    ."1. Ve a Evaluaciones → entra a la instructoría del instructor.\n"
                    ."2. Pulsa el chip \"Docente (n)\" en su card.\n"
                    ."3. Paso 1: \"Descarga el formulario\" .xlsx. Pasalo al docente titular o pegá vos mismo sus respuestas.\n"
                    ."4. Paso 2: subí el archivo y pulsa \"Importar\".\n\n"
                    ."Reglas:\n"
                    ."- Normalmente es una sola fila (un docente por curso).\n"
                    ."- Se guarda con source = csv_import.\n"
                    ."- Podés volver a importar si necesitás corregirla.",
                'suggestions' => [
                    '¿Cómo importo evaluaciones de estudiantes?',
                    '¿Cómo evalúo a un instructor?',
                    '¿Cómo veo todas las evaluaciones (admin)?',
                ],
            ],

            [
                'id' => 'evaluations_admin_panel',
                'keywords' => [
                    'ver todas las evaluaciones', 'panel evaluaciones admin',
                    'panel de evaluaciones', 'todas las evaluaciones',
                    'evaluaciones de toda la facultad', 'reporte de evaluaciones',
                    'evaluaciones por instructor', 'historial de evaluaciones',
                    'exportar evaluaciones', 'descargar evaluaciones consolidadas',
                    'excel evaluaciones consolidado', 'admin evaluaciones',
                ],
                'restricted_to' => ['admin'],
                'restricted_response' => 'El panel global de evaluaciones solo está en el menú del administrador. Si necesitas algo de ahí, escalo con un admin.',
                'response' => "Como admin tenés tres vistas sobre evaluaciones:\n\n"
                    ."1. Evaluaciones (menú lateral): tabla con todas las instructorías evaluadas. Filtros por instructor y por semestre, métricas (total, promedio, pendientes de revisión) y un botón \"Exportar Excel\" por fila que arma un consolidado multi-hoja (resumen + una hoja por tipo).\n"
                    ."2. Detalle de una evaluación: clic en \"Ver\" → vés todas las respuestas agrupadas por tipo (self, coord, students, teacher), promedio por tipo y podés marcarlas como \"Revisado\".\n"
                    ."3. Reporte por instructor: botón \"Por instructor\" → tabla con promedios históricos por tipo y promedio general de cada uno.\n\n"
                    ."Además, podés gestionar las preguntas desde \"Plantillas de preguntas\".",
                'suggestions' => [
                    '¿Cómo gestiono las preguntas de evaluación?',
                    '¿Cómo veo el reporte por instructor?',
                    '¿Cómo finalizo una instructoría?',
                ],
            ],

            [
                'id' => 'evaluations_questions_crud',
                'keywords' => [
                    'preguntas de evaluacion', 'plantilla de preguntas',
                    'plantillas de preguntas', 'crear pregunta', 'editar pregunta',
                    'agregar pregunta', 'desactivar pregunta', 'eliminar pregunta',
                    'reordenar preguntas', 'orden de preguntas', 'crud preguntas',
                    'preguntas autoevaluacion', 'preguntas coordinador',
                    'preguntas estudiantes', 'preguntas docente',
                ],
                'restricted_to' => ['admin'],
                'restricted_response' => 'La gestión de preguntas de evaluación solo está disponible para administradores.',
                'response' => "Para gestionar las preguntas:\n\n"
                    ."1. Menú lateral → Evaluaciones → botón \"Plantillas de preguntas\".\n"
                    ."2. Arriba hay 4 tabs (Autoevaluación, Coordinador, Estudiante, Docente). Cambiá entre ellas para editar las preguntas de cada tipo.\n"
                    ."3. En la barra lateral derecha tenés el formulario para agregar una pregunta nueva (texto, tipo: score 1-10 o texto libre).\n"
                    ."4. En cada pregunta existente:\n"
                    ."   - Lápiz: editar texto / tipo.\n"
                    ."   - Flechas arriba/abajo: cambiar el orden en que aparece.\n"
                    ."   - Switch: activar/desactivar (las inactivas no se muestran en los formularios pero conservan sus respuestas).\n"
                    ."   - Papelera: eliminar (si la pregunta ya tiene respuestas históricas, en lugar de borrar se desactiva para no perder datos).",
                'suggestions' => [
                    '¿Cómo veo todas las evaluaciones (admin)?',
                    '¿Cómo exporto las evaluaciones de un instructor?',
                    '¿Cómo evalúo a un instructor?',
                ],
            ],

            [
                'id' => 'evaluations_notifications',
                'keywords' => [
                    'notificacion autoevaluacion', 'aviso autoevaluacion',
                    'notificacion al coordinador', 'me llega notificacion evaluacion',
                    'aviso cuando instructor evalua', 'notificacion cuando se envia evaluacion',
                ],
                    'response' => "Cuando un instructor envía su autoevaluación, su coordinador encargado recibe una notificación en su burbuja (campanita) arriba a la derecha.\n\n"
                    ."La notificación dice algo como: \"[Instructor] envió su autoevaluación · Grupo: [Materia] · Puntaje: X.XX/10\".\n\n"
                    ."Si el coordinador pulsa la notificación, va directo a su panel de Evaluaciones para revisarla y agregar la suya.\n\n"
                    ."Si el instructor no tiene coordinador asignado, no se envía nada (caso poco común; pasa solo con instructores heredados de antes del aislamiento por coordinación).",
                'suggestions' => [
                    '¿Cómo envío mi autoevaluación?',
                    '¿Cómo evalúo a un instructor?',
                ],
            ],

            // ═══════════════════════════════════════════════════════════
            //  OVERVIEW POR TEMA (responden las "topic pills" del widget)
            // ═══════════════════════════════════════════════════════════
            //
            // Cada vez que el usuario pulsa una pill (p. ej. "Coordinadores",
            // "Instructorías", "Asistencia"), el widget envía:
            //   "¿Qué puedo hacer con <pill>?"
            // Estos intents capturan esas frases y devuelven un menú con
            // todas las acciones posibles + sugerencias clicables que llevan
            // al intent específico de cada acción.

            [
                'id' => 'topic_overview_coordinadores',
                'keywords' => ['coordinadores', 'gestion de coordinadores', 'gestionar coordinadores'],
                'restricted_to' => ['admin'],
                'restricted_response' => "La gestión de coordinadores es exclusiva del administrador. ¿Querés que te ponga en contacto con un administrador?",
                'response' => "Sobre coordinadores puedo guiarte en:\n\n"
                    ."- Crear un coordinador nuevo\n"
                    ."- Editar nombre, correo, contraseña o coordinación\n"
                    ."- Eliminar un coordinador\n\n"
                    ."¿Cuál te interesa?",
                'suggestions' => [
                    '¿Cómo creo un coordinador?',
                    '¿Cómo edito un coordinador?',
                    '¿Cómo elimino un coordinador?',
                ],
            ],

            [
                'id' => 'topic_overview_instructores',
                'keywords' => ['instructores', 'gestion de instructores', 'gestionar instructores'],
                'response' => "Sobre instructores puedo guiarte en:\n\n"
                    ."- Crear un instructor nuevo\n"
                    ."- Editar sus datos o reiniciar su contraseña\n"
                    ."- Eliminar un instructor\n"
                    ."- Ver el listado completo\n\n"
                    ."¿Cuál te interesa?",
                'suggestions' => [
                    '¿Cómo creo un instructor?',
                    '¿Cómo edito un instructor?',
                    '¿Cómo elimino un instructor?',
                ],
            ],

            [
                'id' => 'topic_overview_groups',
                'keywords' => ['grupos de clase', 'grupo de clase', 'gestion de grupos', 'gestionar grupos'],
                'restricted_to' => ['coordinator'],
                'restricted_response' => "La gestión de grupos de clase es del coordinador. ¿Querés que te ponga en contacto con un administrador?",
                'response' => "Sobre grupos de clase puedo guiarte en:\n\n"
                    ."- Crear, editar o eliminar un grupo\n"
                    ."- Asignar (o cambiar) el instructor del grupo\n"
                    ."- Ver los estudiantes inscritos\n"
                    ."- Importar estudiantes desde Excel o CSV\n\n"
                    ."¿Cuál te interesa?",
                'suggestions' => [
                    '¿Cómo creo un grupo?',
                    '¿Cómo asigno un instructor a un grupo?',
                    '¿Cómo importo estudiantes?',
                ],
            ],

            [
                'id' => 'topic_overview_instructorias',
                'keywords' => ['instructorias', 'que son las instructorias'],
                'restricted_to' => ['coordinator'],
                'restricted_response' => "La vista de instructorías está en el panel del coordinador. ¿Querés que te ponga en contacto con un administrador?",
                'response' => "Sobre instructorías (sesiones que dieron los instructores) puedo guiarte en:\n\n"
                    ."- Ver las sesiones que ha dado cada instructor\n"
                    ."- Revisar fecha, horario, duración y asistentes\n"
                    ."- Exportar el histórico a Excel\n\n"
                    ."¿Cuál te interesa?",
                'suggestions' => [
                    '¿Cómo veo las instructorías de un instructor?',
                    '¿Cómo exporto las instructorías a Excel?',
                ],
            ],

            [
                'id' => 'topic_overview_mis_instructores',
                'keywords' => ['mis instructores', 'mis tutores'],
                'restricted_to' => ['coordinator'],
                'restricted_response' => "El menú \"Mis instructores\" pertenece al coordinador. ¿Querés que te ponga en contacto con un administrador?",
                'response' => "Sobre \"Mis instructores\" puedo guiarte en:\n\n"
                    ."- Crear un instructor nuevo (queda en tu coordinación)\n"
                    ."- Editar o eliminar un instructor\n"
                    ."- Asignarlo a un grupo de clase\n\n"
                    ."¿Cuál te interesa?",
                'suggestions' => [
                    '¿Cómo creo un instructor?',
                    '¿Cómo elimino un instructor?',
                    '¿Cómo asigno un instructor a un grupo?',
                ],
            ],

            [
                'id' => 'topic_overview_iniciar_sesion',
                'keywords' => ['iniciar sesion'],
                'response' => "\"Iniciar sesión\" puede significar dos cosas en Instructor Hub:\n\n"
                    ."1. Login: entrar al sistema con tu correo y contraseña.\n"
                    ."2. Sesión QR (instructor): abrir el QR para que los estudiantes marquen asistencia.\n\n"
                    ."¿Cuál te interesa?",
                'suggestions' => [
                    '¿Qué hago si no puedo iniciar sesión?',
                    '¿Cómo inicio una sesión con QR?',
                    '¿Cómo finalizo la sesión QR?',
                ],
            ],

            [
                'id' => 'topic_overview_asistencia',
                'keywords' => ['asistencia'],
                'response' => "Sobre asistencia puedo guiarte en:\n\n"
                    ."- Tomar asistencia con código QR (instructor)\n"
                    ."- Finalizar la sesión QR\n"
                    ."- Ver la matriz de asistencia (instructor)\n"
                    ."- Exportar la asistencia a Excel\n"
                    ."- Marcar asistencia como estudiante (escaneando el QR)\n\n"
                    ."¿Cuál te interesa?",
                'suggestions' => [
                    '¿Cómo inicio una sesión con QR?',
                    '¿Cómo veo la matriz de asistencia?',
                    '¿Cómo exporto la asistencia a Excel?',
                ],
            ],

            [
                'id' => 'topic_overview_exportar_excel',
                'keywords' => ['exportar excel', 'exportar a excel', 'exportar reportes'],
                'response' => "Los reportes en Excel se generan desde dos lugares:\n\n"
                    ."- Instructor: \"Asistencia\" → entrar a la instructoría → botón verde \"Exportar Excel\". Baja una matriz estudiantes × sesiones más un resumen.\n"
                    ."- Coordinador: \"Instructorías\" → entrar al instructor → botón verde \"Exportar Excel\". Baja el detalle de todas sus sesiones más un resumen.\n\n"
                    ."¿Cuál te interesa?",
                'suggestions' => [
                    '¿Cómo exporto la asistencia a Excel?',
                    '¿Cómo exporto las instructorías a Excel?',
                ],
            ],

            [
                'id' => 'topic_overview_evaluaciones',
                'keywords' => [
                    'que puedo hacer con evaluaciones',
                    'gestion de evaluaciones', 'gestionar evaluaciones',
                    'menu de evaluaciones', 'modulo de evaluaciones general',
                ],
                'response' => "Sobre evaluaciones puedo guiarte en:\n\n"
                    ."- Cómo funciona el módulo (los 4 tipos: self / coordinador / estudiantes / docente)\n"
                    ."- Cómo se habilitan (instructoría debe estar FINALIZADO)\n"
                    ."- Autoevaluación del instructor\n"
                    ."- Evaluación del coordinador\n"
                    ."- Importar evaluaciones de estudiantes o del docente titular\n"
                    ."- Panel global del admin y reporte por instructor\n"
                    ."- Gestionar las preguntas (solo admin)\n\n"
                    ."¿Cuál te interesa?",
                'suggestions' => [
                    '¿Cómo funcionan las evaluaciones?',
                    '¿Cómo envío mi autoevaluación?',
                    '¿Cómo evalúo a un instructor?',
                    '¿Cómo importo evaluaciones de estudiantes?',
                ],
            ],

            [
                'id' => 'topic_overview_autoevaluacion',
                'keywords' => [
                    'que puedo hacer con autoevaluacion',
                    'menu de autoevaluacion', 'opciones de autoevaluacion',
                ],
                'restricted_to' => ['instructor'],
                'restricted_response' => "La autoevaluación es del instructor. Si tu rol no es instructor, puedo explicarte el flujo igual.",
                'response' => "Sobre tu autoevaluación puedo guiarte en:\n\n"
                    ."- Cómo enviarla paso a paso\n"
                    ."- Por qué a veces no aparece habilitada (instructoría aún Activo)\n"
                    ."- Cómo editar una autoevaluación ya enviada\n\n"
                    ."¿Cuál te interesa?",
                'suggestions' => [
                    '¿Cómo envío mi autoevaluación?',
                    '¿Por qué no me aparece la evaluación?',
                    '¿Cómo veo el estado de mi autoevaluación?',
                ],
            ],

            // ═══════════════════════════════════════════════════════════
            //  ESCALADO
            // ═══════════════════════════════════════════════════════════

            [
                'id' => 'contact_admin',
                'keywords' => [
                    'contactar admin', 'contactar al admin', 'contactar administrador',
                    'contactar al administrador', 'hablar con admin', 'hablar con el admin',
                    'hablar con un administrador', 'hablar con el administrador',
                    'soporte humano', 'hablar con alguien', 'solicitar soporte',
                    'pedir ayuda al admin', 'pedir ayuda admin', 'enviar solicitud',
                    'necesito un humano',
                    'no recibo respuesta del admin', 'no me responde el admin',
                    'sin respuesta del admin', 'no recibo respuesta',
                ],
                'response' => "Puedo pasarle tu solicitud a un administrador. Voy a abrir un formulario corto:\n\n"
                    ."1. Confirma tu nombre (precargado con el de tu cuenta si estás logueado).\n"
                    ."2. Confirma el correo donde queres recibir la respuesta.\n"
                    ."3. Pulsa \"Enviar solicitud\".\n\n"
                    ."Si todo va bien verás \"Listo. Un administrador recibió tu solicitud y se pondrá en contacto contigo lo antes posible al correo {tucorreo}.\" El admin lo ve como notificación en su campanita.\n\n"
                    .'¿Querés que te ponga en contacto con un administrador?',
                'suggestions' => [
                    '¿Olvidé mi contraseña?',
                    '¿Mi carnet no está en la lista?',
                    '¿Algo no funciona, qué hago?',
                ],
            ],

            [
                'id' => 'report_bug',
                'keywords' => [
                    'reportar bug', 'bug', 'error del sistema', 'no funciona',
                    'se rompio', 'algo fallo', 'reportar problema', 'reportar error',
                    'tengo un problema', 'falla',
                ],
                'response' => "Si encontraste un error, vamos a contarle al administrador con todos los detalles:\n\n"
                    ."1. Anota el menú donde estabas (ej. \"Grupos de clase\", \"Iniciar sesión\", etc.).\n"
                    ."2. Anota qué estabas intentando hacer (ej. \"importar Excel\", \"finalizar sesión\").\n"
                    ."3. Copia el mensaje exacto que viste, si hay alguno.\n"
                    ."4. Pulsa \"Contactar administrador\" abajo para abrir el formulario de soporte.\n\n"
                    .'El admin recibe tu nombre, correo, la pregunta y la última respuesta del bot como contexto. ¿Querés que te ponga en contacto con un administrador?',
                'suggestions' => [
                    '¿Cómo solicito soporte humano?',
                    '¿Olvidé mi contraseña?',
                    '¿Qué puedes hacer?',
                ],
            ],

        ];
    }
}
