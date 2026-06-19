<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\FicabotSupportRequested;
use App\Services\FicabotKnowledgeBase;
use App\Services\FicabotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

/**
 * Endpoints AJAX del widget FICABOT que vive en `components/ficabot.blade.php`.
 *
 *  - ask():      recibe el mensaje del usuario y devuelve la respuesta del bot
 *                  (rule-based, sin OpenAI: ver FicabotService + FicabotKnowledgeBase).
 *  - escalate(): registra una solicitud de soporte humano y le envía una
 *                  notificación a todos los administradores (campanita del admin).
 *
 *  Notas:
 *      - Ninguno de los dos endpoints requiere el middleware `auth` para que
 *          un eventual problema de sesión expirada no rompa el chat (devolvía
 *          401 antes). En su lugar, el método escalate() usa Auth::user() de
 *          forma opcional: si hay sesión válida, vincula la solicitud al
 *          usuario; si no, igual la procesa con el nombre+correo que el
 *          usuario llenó en el formulario.
 */
class FicabotController extends Controller
{
    /**
     * Devuelve la respuesta del bot a un mensaje del usuario.
     *
     * Request body:
     *  - message (string, requerido): la pregunta nueva del usuario.
     *  - history (array, opcional):   lista de {role:'user'|'assistant', content:string}
     *                                  con el historial reciente (máx 20 entradas).
     *
     * Respuesta:
     *  - reply              (string): texto que muestra el bot.
     *  - can_escalate       (bool):   true cuando conviene mostrar el botón "Contactar admin".
     *  - suggestions        (array):  chips de seguimiento clicables.
     *  - open_contact_form  (bool):   atajo de conversación: si el usuario
     *                                  respondió "sí" a un ofrecimiento de
     *                                  escalado, el widget abre directo el
     *                                  formulario sin requerir que pulse el botón.
     */
    public function ask(Request $request, FicabotService $bot): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ]);

        $result = $bot->ask($data['message'], $data['history'] ?? []);

        return response()->json([
            'reply' => $result['reply'],
            'can_escalate' => $result['can_escalate'],
            'suggestions' => $result['suggestions'] ?? [],
            'open_contact_form' => $result['open_contact_form'] ?? false,
        ]);
    }

    /**
     * Devuelve sugerencias iniciales cuando el usuario abre el chat.
     * (Endpoint opcional usado por el widget para refrescar los chips.)
     */
    public function suggestions(FicabotKnowledgeBase $kb): JsonResponse
    {
        return response()->json([
            'suggestions' => $kb->defaultSuggestions(Auth::user()?->roleSlug()),
        ]);
    }

    /**
     * Crea una solicitud de soporte: notifica a todos los administradores.
     *
     * Como el endpoint NO está detrás del middleware `auth`, aceptamos también
     * solicitudes de usuarios cuya sesión se haya invalidado: en ese caso,
     * usamos solo los datos de contacto del formulario.
     *
     * Request body:
     *  - contact_name   (string, requerido): nombre con el que quiere ser contactado.
     *  - contact_email  (string, requerido): correo donde recibir la respuesta.
     *  - contact_reason (string, requerido): motivo escrito por el usuario en el formulario.
     *  - question       (string, requerido): la duda original del usuario / contexto de la conversación.
     *  - bot_reply      (string, opcional):  última respuesta del bot, para contexto.
     */
    public function escalate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:180'],
            'contact_reason' => ['required', 'string', 'min:5', 'max:1000'],
            'question' => ['required', 'string', 'max:2000'],
            'bot_reply' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ], [
            'contact_name.required' => 'Debes ingresar tu nombre.',
            'contact_email.required' => 'Debes ingresar un correo de contacto.',
            'contact_email.email' => 'El correo de contacto no es válido.',
            'contact_reason.required' => 'Debes describir el motivo.',
            'contact_reason.min' => 'El motivo es muy corto.',
            'question.required' => 'No hay una pregunta para enviar al administrador.',
        ]);

        $admins = User::query()
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->get();

        if ($admins->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'No hay administradores configurados en este momento. '.
                    'Intenta más tarde o contacta directamente al equipo de FICA.',
            ]);
        }

        Notification::send($admins, new FicabotSupportRequested(
            requester: Auth::user(),
            question: $data['question'],
            botReply: $data['bot_reply'] ?? null,
            contactName: $data['contact_name'],
            contactEmail: $data['contact_email'],
            reason: $data['contact_reason'],
        ));

        return response()->json([
            'ok' => true,
            'message' => 'Listo. Un administrador recibió tu solicitud y se pondrá en contacto contigo lo antes posible al correo '.$data['contact_email'].'.',
        ]);
    }
}
