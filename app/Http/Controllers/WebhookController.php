<?php

namespace App\Http\Controllers;

use App\Events\MessageReceived;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\MessageQuotaService;
use App\Services\ConversationAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{



    public function receive(Request $request, MessageQuotaService $messageQuota, ConversationAssignmentService $assignmentService)
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
    
    // 🏢 Información de departamento desde n8n (opcional)
    $assignedDepartment = $payload['department'] ?? null;
    $assignedAgentId = $payload['agent_id'] ?? null;

    $twilioSid = $payload['messageSid']
        ?? ($payload['raw']['data']['messageSid'] ?? null);

    if (!$from) {
        // Log::warning('Webhook sin número origen', $request->all());
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
    dispatch(function () use ($conversation, $body, $twilioSid, $from, $assignedDepartment, $assignedAgentId, $assignmentService) {

        // 💾 Guardar mensaje inbound
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'body'            => $body,
            'direction'       => 'inbound',
            'sender_type'     => 'user',
            'twilio_sid'      => $twilioSid,
        ]);

        // 🏢 Procesar asignación desde n8n si viene especificada
        $this->handleN8nAssignment($conversation, $assignedDepartment, $assignedAgentId, $assignmentService);

        // 🔄 Actualizar última actividad
        $conversation->update([
            'last_message_at' => now()
        ]);

        // 📡 Enviar a frontend (WebSocket)
        broadcast(new MessageReceived($message))->toOthers();

        // 📊 Log
        // Log::info('Mensaje inbound guardado', [
        //     'phone'        => $from,
        //     'conversation' => $conversation->id,
        //     'is_human'     => $conversation->is_human,
        //     'assigned_department' => $assignedDepartment,
        //     'assigned_agent_id' => $assignedAgentId,
        // ]);

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

        // Log::info('Webhook outbound received', $payload);

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
            // Log::warning('Outbound ignorado: faltan campos requeridos', $payload);
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

            // Log::info('Mensaje outbound guardado', [
            //     'conversation' => $conversation->id,
            //     'twilio_sid'   => $twilioSid,
            // ]);
        })->afterResponse();

        return response()->json([
            'status'          => 'ok',
            'queued'          => true,
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Endpoint específico para manejar cambios de asignación desde n8n
     * Se llama cuando el cliente selecciona un departamento en el menú
     */
    public function assignToDepartment(Request $request, ConversationAssignmentService $assignmentService)
    {
        $payload = $request->all();
        
        // Log::info('Webhook asignación de departamento desde n8n', $payload);

        $conversationId = $payload['conversation_id'] ?? null;
        $department = $payload['department'] ?? null;
        $agentId = $payload['agent_id'] ?? null;
        $from = $payload['from'] ?? $payload['phone'] ?? null;

        if (!$conversationId && !$from) {
            Log::warning('Asignación ignorada: falta conversation_id o from', $payload);
            return response()->json(['status' => 'ignored', 'reason' => 'missing conversation_id or from'], 200);
        }

        try {
            $conversation = null;
            
            if ($conversationId) {
                $conversation = Conversation::find($conversationId);
            } elseif ($from) {
                // Buscar por número de teléfono
                $contact = Contact::where('phone', $from)->first();
                if ($contact) {
                    $conversation = Conversation::where('contact_id', $contact->id)
                        ->where('status', 'active')
                        ->first();
                }
            }

            if (!$conversation) {
                Log::warning('Asignación ignorada: conversación no encontrada', [
                    'conversation_id' => $conversationId,
                    'from' => $from
                ]);
                return response()->json(['status' => 'ignored', 'reason' => 'conversation not found'], 200);
            }

            $assigned = false;
            $assignedTo = null;

            if ($agentId) {
                // Asignar a asesor específico
                $agent = User::find($agentId);
                if ($agent && $agent->isAvailableForAssignment()) {
                    $conversation->reassignTo($agent);
                    $assigned = true;
                    $assignedTo = [
                        'type' => 'agent',
                        'id' => $agent->id,
                        'name' => $agent->name,
                        'department' => $agent->department_name
                    ];
                    // Log::info('Conversación asignada a asesor específico desde n8n', [
                    //     'conversation_id' => $conversation->id,
                    //     'agent_id' => $agent->id,
                    //     'agent_name' => $agent->name
                    // ]);
                } else {
                    Log::warning('Asesor no disponible para asignación', [
                        'agent_id' => $agentId,
                        'agent_active' => $agent?->is_active,
                        'agent_department' => $agent?->department
                    ]);
                }
            } elseif ($department && array_key_exists($department, User::DEPARTMENTS)) {
                // Asignar a departamento
                $assigned = $assignmentService->assignToDepartment($conversation, $department);
                
                if ($assigned) {
                    $conversation->refresh();
                    $assignedTo = [
                        'type' => 'department',
                        'department' => $department,
                        'department_name' => User::DEPARTMENTS[$department],
                    ];
                    // Log::info('Conversación asignada a departamento desde n8n', [
                    //     'conversation_id' => $conversation->id,
                    //     'department' => $department,
                    // ]);
                }
            } else {
                Log::warning('Parámetros de asignación no válidos', [
                    'department' => $department,
                    'agent_id' => $agentId
                ]);
            }

            return response()->json([
                'status' => $assigned ? 'assigned' : 'not_assigned',
                'conversation_id' => $conversation->id,
                'assigned_to' => $assignedTo,
                'message' => $assigned ? 'Conversación asignada exitosamente' : 'No se pudo asignar la conversación'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en asignación desde n8n', [
                'error' => $e->getMessage(),
                'payload' => $payload,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno en asignación'
            ], 500);
        }
    }

    /**
     * Manejar asignación desde n8n en el método receive
     */
    private function handleN8nAssignment(Conversation $conversation, ?string $department, ?int $agentId, ConversationAssignmentService $assignmentService): void
    {
        if (!$department && !$agentId) {
            return;
        }

        try {
            if ($agentId) {
                // Asignar a asesor específico
                $agent = User::find($agentId);
                if ($agent && $agent->isAvailableForAssignment()) {
                    $conversation->assignToDepartment($agent->department, $agent);
                    // Log::info('Conversación auto-asignada a asesor desde n8n', [
                    //     'conversation_id' => $conversation->id,
                    //     'agent_id' => $agent->id,
                    //     'agent_name' => $agent->name,
                    //     'department' => $agent->department
                    // ]);
                }
            } elseif ($department && array_key_exists($department, User::DEPARTMENTS)) {
                // Asignar a departamento
                $assigned = $assignmentService->assignToDepartment($conversation, $department);
                if ($assigned) {
                    // Log::info('Conversación auto-asignada a departamento desde n8n', [
                    //     'conversation_id' => $conversation->id,
                    //     'department' => $department,
                    // ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error en auto-asignación desde n8n', [
                'conversation_id' => $conversation->id,
                'department' => $department,
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
