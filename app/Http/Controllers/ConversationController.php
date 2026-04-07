<?php

namespace App\Http\Controllers;

use App\Events\MessageReceived;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessageQuotaService;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    /**
     * Lista conversaciones activas.
     * - Admin: ve todas.
     * - Asesor: solo las asignadas a él.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Conversation::with(['contact', 'lastMessage', 'assignedAgent'])
            ->where('status', 'active');

        if ($user->hasRole('asesor')) {
            $query->where('assigned_to', $user->id);
        }

        $conversations = $query->orderByDesc('updated_at')
            ->get()
            ->map(fn($c) => [
                'id'           => $c->id,
                'is_human'     => $c->is_human,
                'status'       => $c->status,
                'contact'      => [
                    'id'    => $c->contact->id,
                    'phone' => $c->contact->phone,
                    'name'  => $c->contact->name,
                ],
                'assigned_to'  => $c->assignedAgent ? [
                    'id'   => $c->assignedAgent->id,
                    'name' => $c->assignedAgent->name,
                ] : null,
                'last_message' => $c->lastMessage?->body,
                'updated_at'   => $c->updated_at,
            ]);

        return response()->json($conversations);
    }

    /**
     * Devuelve todos los mensajes de una conversación.
     */
    public function messages(Conversation $conversation)
    {
        return response()->json(
            $conversation->messages()->orderBy('created_at')->get()
        );
    }

    /**
     * Activa/desactiva el modo humano de una conversación.
     * is_human = true  → el agente humano responde desde el front
     * is_human = false → n8n / IA retoma el control
     */
    public function toggleHuman(Conversation $conversation)
    {
        $conversation->update(['is_human' => !$conversation->is_human]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'is_human'        => $conversation->is_human,
        ]);
    }

    /**
     * El agente humano envía un mensaje desde el front.
     * Solo permitido cuando is_human = true.
     */
    public function sendHuman(
        Request $request,
        Conversation $conversation,
        TwilioService $twilio,
        MessageQuotaService $messageQuota
    )
    {

        Log::info('sendHuman [1/6] inicio', [
            'conversation_id' => $conversation->id,
            'is_human'        => $conversation->is_human,
            'request'         => $request->all(),
        ]);

        $quotaSnapshot = $messageQuota->snapshot();
        $messageQuota->notifyIfChanged($quotaSnapshot);

        Log::info('sendHuman [2/6] quota ok', ['blocked' => $quotaSnapshot['blocked']]);

        if ($messageQuota->isBlocked($quotaSnapshot)) {
            return response()->json($messageQuota->blockedPayload($quotaSnapshot), 429);
        }

        if (!$conversation->is_human) {
            return response()->json(['error' => 'La conversación no está en modo humano.'], 422);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:1600',
        ]);

        Log::info('sendHuman [3/6] validado, enviando a Twilio', [
            'to'   => $conversation->contact->phone,
            'body' => $validated['body'],
        ]);

        // Enviar el mensaje real por WhatsApp vía Twilio
        try {
            $twilioSid = $twilio->sendWhatsApp(
                $conversation->contact->phone,
                $validated['body']
            );
        } catch (\Throwable $e) {
            Log::error('sendHuman Twilio exception', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al enviar el mensaje.'], 500);
        }

        Log::info('sendHuman [4/6] Twilio respondió', ['twilio_sid' => $twilioSid]);

        if (!$twilioSid) {
            return response()->json(['error' => 'Twilio no pudo enviar el mensaje.'], 502);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'body'            => $validated['body'],
            'direction'       => 'outbound',
            'sender_type'     => 'human_agent',
            'twilio_sid'      => $twilioSid,
        ]);

        Log::info('sendHuman [5/6] mensaje guardado', ['message_id' => $message->id]);

        broadcast(new MessageReceived($message));

        $quotaSnapshot = $messageQuota->snapshot();
        $messageQuota->notifyIfChanged($quotaSnapshot);

        Log::info('sendHuman [6/6] completado');

        return response()->json([
            'status'     => 'sent',
            'message_id' => $message->id,
            'quota'      => $quotaSnapshot,
        ]);
    }
}
