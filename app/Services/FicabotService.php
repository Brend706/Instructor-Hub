<?php

namespace App\Services;

use App\Models\User;

/**
 * Lógica del asistente FICABOT.
 *
 *  - Es un bot rule-based 100% local (sin OpenAI ni servicios externos):
 *      ask() recibe el mensaje del usuario, consulta FicabotKnowledgeBase y
 *      delega a resolve() para devolver la respuesta apropiada al rol.
 *  - Cuando un intent define `restricted_to` y el rol del usuario no está
 *      en la lista (p. ej. un instructor preguntando "¿Cómo creo una cuenta
 *      de instructor?"), el servicio devuelve `restricted_response` y activa
 *      el flag `can_escalate` para que el widget muestre el botón de
 *      contactar al administrador.
 *  - Cuando ningún intent coincide, devuelve un mensaje "no entendí" + el
 *      botón de escalado.
 *
 *  Ventajas frente a OpenAI:
 *      - 0 dependencias externas, sin costos, sin API keys.
 *      - Respuestas predecibles y revisables.
 *      - Funciona offline.
 *      - Conoce el sistema por rol y aplica permisos automáticamente.
 */
class FicabotService
{
    public function __construct(private FicabotKnowledgeBase $kb)
    {
    }

    /**
     * Procesa el mensaje del usuario y devuelve la respuesta del bot.
     *
     * Atajos de conversación (ANTES de buscar en la KB):
     *   - "sí" / "ok" / "dale" tras un ofrecimiento de contacto → activa el
     *     flag `open_contact_form` para que el widget abra directamente el
     *     formulario de soporte (nombre + correo).
     *   - "no" / "no gracias" tras lo mismo → acuse breve y termina ahí.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, can_escalate: bool, suggestions: array<int,string>, open_contact_form?: bool}
     */
    public function ask(string $message, array $history = []): array
    {
        $role = auth()->user()?->roleSlug();

        if ($this->isAffirmativeAfterEscalation($message, $history)) {
            return [
                'reply' => "¡Listo! Te abro el formulario de contacto. Confirmá tu nombre y correo y un administrador te responderá lo antes posible.",
                'can_escalate' => false,
                'suggestions' => [],
                'open_contact_form' => true,
            ];
        }

        if ($this->isNegativeAfterEscalation($message, $history)) {
            return [
                'reply' => "Sin problema. Si después cambiás de opinión, pedíme \"contactar administrador\" o usá el botón verde cuando aparezca. ¿Te ayudo con algo más?",
                'can_escalate' => false,
                'suggestions' => $this->kb->defaultSuggestions($role),
            ];
        }

        $intent = $this->kb->match($message);

        if ($intent === null) {
            return [
                'reply' => $this->fallbackReply(),
                'can_escalate' => true,
                'suggestions' => $this->kb->defaultSuggestions($role),
            ];
        }

        $resolved = $this->kb->resolve($intent, $role);

        return [
            'reply' => $resolved['reply'],
            'can_escalate' => $resolved['can_escalate'],
            'suggestions' => $resolved['suggestions'],
        ];
    }

    /**
     * Detecta una respuesta afirmativa CORTA (1-3 palabras) inmediatamente
     * después de que el bot ofreciera escalar al administrador.
     * Solo dispara el atajo si las dos condiciones se cumplen, para no
     * confundirlo con preguntas reales que contengan "sí" o "ok".
     *
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function isAffirmativeAfterEscalation(string $message, array $history): bool
    {
        $normalized = $this->kb->normalize($message);
        if ($normalized === '') {
            return false;
        }
        $words = explode(' ', $normalized);
        if (count($words) > 3) {
            return false;
        }

        $affirmatives = [
            'si', 'sii', 'siii', 'sip', 'sipi',
            'ok', 'okay', 'okey', 'oki',
            'va', 'vale', 'dale',
            'claro', 'obvio',
            'por favor', 'porfa', 'porfavor', 'por fa',
            'yes', 'yep', 'yeah',
            'si porfa', 'si por favor', 'si gracias', 'ok gracias', 'dale gracias',
            'claro que si', 'esta bien', 'bueno',
            'me parece', 'perfecto',
        ];

        if (! in_array($normalized, $affirmatives, true)) {
            return false;
        }

        return $this->lastAssistantOfferedEscalation($history);
    }

    /**
     * Mismo principio, pero para "no" / "no gracias" tras un ofrecimiento
     * de escalado: corta la conversación de forma amable.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function isNegativeAfterEscalation(string $message, array $history): bool
    {
        $normalized = $this->kb->normalize($message);
        if ($normalized === '') {
            return false;
        }
        $words = explode(' ', $normalized);
        if (count($words) > 3) {
            return false;
        }

        $negatives = [
            'no', 'nop', 'nope', 'nah',
            'no gracias', 'nada', 'mejor no', 'no por ahora', 'no quiero',
            'mas tarde', 'despues', 'nel',
        ];

        if (! in_array($normalized, $negatives, true)) {
            return false;
        }

        return $this->lastAssistantOfferedEscalation($history);
    }

    /**
     * Devuelve true si el ÚLTIMO mensaje del asistente en el historial
     * incluía una invitación explícita a contactar al administrador.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function lastAssistantOfferedEscalation(array $history): bool
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $msg = $history[$i];
            if (($msg['role'] ?? null) !== 'assistant') {
                continue;
            }
            $content = mb_strtolower((string) ($msg['content'] ?? ''));
            foreach ([
                'contacto con un administrador',
                'pongo en contacto',
                'contactar al administrador',
                'contactar a un administrador',
                'paso tu solicitud al administrador',
                'le paso tu solicitud',
            ] as $needle) {
                if (str_contains($content, $needle)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    /**
     * Mensaje cuando ningún intent coincide.
     * Sigue el manifiesto de personalidad de Lumi: en vez de escalar
     * inmediatamente, primero le pide al usuario MÁS DETALLES (mensaje
     * exacto, pantalla donde está, qué intentaba hacer). Como contingencia,
     * incluye la frase canónica de escalado para que el widget muestre el
     * botón "Contactar administrador" abajo, por si el usuario prefiere
     * saltar directo al humano.
     */
    private function fallbackReply(): string
    {
        /** @var User|null $user */
        $user = auth()->user();
        $name = $user?->name ? ' '.explode(' ', $user->name)[0] : '';

        return "Mmm, no estoy segura de cómo responder eso{$name}. "
            ."¿Podrías darme un poco más de contexto?\n\n"
            ."Por ejemplo:\n"
            ."- ¿En qué pantalla estás (menú o URL)?\n"
            ."- ¿Qué intentabas hacer cuando ocurrió?\n"
            ."- Si hay un mensaje de error, copiámelo exacto.\n\n"
            ."Yo manejo instructores, coordinadores, grupos de clase, asistencia "
            ."por QR, importación de estudiantes, exportación a Excel y "
            ."notificaciones.\n\n"
            ."Si preferís, ¿querés que te ponga en contacto con un administrador?";
    }
}
