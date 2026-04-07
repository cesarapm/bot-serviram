<?php

namespace App\Http\Controllers;

use App\Events\MessageReceived;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\MessageQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{



    public function receive(Request $request, MessageQuotaService $messageQuota)
{
    // 📊 Control de cuota
    
    // Log::info($request->all());
    $quotaSnapshot = $messageQuota->snapshot();
    $messageQuota->notifyIfChanged($quotaSnapshot);

    if ($messageQuota->isBlocked($quotaSnapshot)) {
        return response()->json([
            'status' => 'blocked',
            ...$messageQuota->blockedPayload($quotaSnapshot),
        ], 429);
    }

    // 📦 Payload desde n8n
    $payload = $request->input('data', []);

    $from = $payload['from'] ?? $payload['fromE164'] ?? null;
    $to   = $payload['to'] ?? $payload['toE164'] ?? null;
    $body = $payload['text'] ?? $payload['body'] ?? '';

    $twilioSid = $payload['messageSid']
        ?? ($payload['raw']['data']['messageSid'] ?? null);

    if (!$from) {
        Log::warning('Webhook sin número origen', $request->all());
        return response()->json(['status' => 'ignored'], 200);
    }

    // 👤 Buscar o crear contacto
    $contact = Contact::firstOrCreate(
        ['phone' => $from],
        ['name' => null]
    );

    // 💬 Buscar o crear conversación activa
    $conversation = Conversation::firstOrCreate(
        [
            'contact_id' => $contact->id,
            'status'     => 'active'
        ],
        [
            'is_human' => false
        ]
    );

    // ⚡ RESPUESTA INMEDIATA (para n8n)
    $response = [
        'status'          => 'ok',
        'conversation_id' => $conversation->id,
        'is_human'        => $conversation->is_human, // 🔥 CLAVE
        'quota'           => $quotaSnapshot,
    ];

    // 🚀 PROCESO EN SEGUNDO PLANO
    dispatch(function () use ($conversation, $body, $twilioSid, $from) {

        // 💾 Guardar mensaje inbound
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'body'            => $body,
            'direction'       => 'inbound',
            'sender_type'     => 'user',
            'twilio_sid'      => $twilioSid,
        ]);

        // 🔄 Actualizar última actividad
        $conversation->update([
            'last_message_at' => now()
        ]);

        // 📡 Enviar a frontend (WebSocket)
        broadcast(new MessageReceived($message))->toOthers();

        // 📊 Log
        Log::info('Mensaje inbound guardado', [
            'phone'        => $from,
            'conversation' => $conversation->id,
            'is_human'     => $conversation->is_human,
        ]);

    })->afterResponse();

    // ⚡ RESPUESTA RÁPIDA (IMPORTANTE)
    return response()->json($response, 200);
}

    /**
     * Recibe el mensaje de respuesta que generó la IA en n8n
     * y lo guarda para mantener el hilo de la conversación.
     */
    public function storeOutbound(Request $request, MessageQuotaService $messageQuota)
    {
        $payload = $request->all();

        Log::info('Webhook outbound received', $payload);

        // Control de cuota
        $quotaSnapshot = $messageQuota->snapshot();
        $messageQuota->notifyIfChanged($quotaSnapshot);

        if ($messageQuota->isBlocked($quotaSnapshot)) {
            return response()->json([
                'status' => 'blocked',
                ...$messageQuota->blockedPayload($quotaSnapshot),
            ], 429);
        }

        $conversationId = $payload['conversation_id'] ?? null;
        $messageBody    = $payload['body'] ?? null;
        $twilioSid      = $payload['twilio_sid'] ?? null;

        if (!$conversationId || !$messageBody) {
            Log::warning('Outbound ignorado: faltan campos requeridos', $payload);
            return response()->json(['status' => 'ignored', 'reason' => 'missing required fields'], 200);
        }

        $conversation = Conversation::find((int) $conversationId);

        if (!$conversation) {
            Log::warning('Outbound ignorado: conversación no encontrada', [
                'conversation_id' => $conversationId,
            ]);
            return response()->json(['status' => 'ignored', 'reason' => 'conversation not found'], 200);
        }

        dispatch(function () use ($conversation, $messageBody, $twilioSid) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'body'            => $messageBody,
                'direction'       => 'outbound',
                'sender_type'     => 'bot',
                'twilio_sid'      => $twilioSid,
            ]);

            $conversation->update(['last_message_at' => now()]);

            broadcast(new MessageReceived($message));

            Log::info('Mensaje outbound guardado', [
                'conversation' => $conversation->id,
                'twilio_sid'   => $twilioSid,
            ]);
        })->afterResponse();

        return response()->json([
            'status'          => 'ok',
            'queued'          => true,
            'conversation_id' => $conversation->id,
        ]);
    }
}
