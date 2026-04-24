<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use App\Services\ConversationAssignmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class AutoAssignConversation implements ShouldQueue
{
    protected ConversationAssignmentService $assignmentService;

    public function __construct(ConversationAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * Handle the event.
     */
    public function handle(MessageReceived $event): void
    {
        $message = $event->message;
        $conversation = $message->conversation;

        // Solo procesar si la auto-asignación está habilitada
        if (!config('messaging.auto_assignment_enabled', true)) {
            return;
        }

        // Solo procesar mensajes entrantes de clientes
        if ($message->direction !== 'incoming' || $message->sender_type !== 'contact') {
            return;
        }

        // Solo asignar si la conversación no está asignada a un humano
        if ($conversation->is_human && $conversation->assigned_to) {
            return;
        }

        // IMPORTANTE: No auto-asignar si n8n ya asignó en los últimos 5 segundos
        // Esto evita conflictos entre auto-asignación y asignación manual desde n8n
        if ($this->wasRecentlyAssignedByN8n($conversation)) {
            // Log::info('Saltando auto-asignación: conversación recientemente asignada por n8n', [
            //     'conversation_id' => $conversation->id
            // ]);
            return;
        }

        try {
            // Determinar departamento basado en el contenido del mensaje
            $department = $this->assignmentService->determineDepartmentByQuery($message->content);

            // Log::info('Intentando auto-asignar conversación', [
            //     'conversation_id' => $conversation->id,
            //     'determined_department' => $department,
            //     'message_content' => $message->content
            // ]);

            // Intentar asignar la conversación
            $assigned = $this->assignmentService->assignToAvailableAgent($conversation, $department);

            if ($assigned) {
                // Log::info('Conversación auto-asignada exitosamente', [
                //     'conversation_id' => $conversation->id,
                //     'assigned_to' => $conversation->fresh()->assigned_to,
                //     'department' => $department
                // ]);
            } else {
                Log::warning('No se pudo auto-asignar conversación - sin asesores disponibles', [
                    'conversation_id' => $conversation->id,
                    'department' => $department
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error en auto-asignación de conversación', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Verificar si la conversación fue asignada recientemente por n8n
     * para evitar conflictos con auto-asignación
     */
    private function wasRecentlyAssignedByN8n(Conversation $conversation): bool
    {
        // Verificar si la conversación fue asignada en los últimos 5 segundos
        if ($conversation->assigned_to && $conversation->updated_at) {
            $secondsSinceUpdate = now()->diffInSeconds($conversation->updated_at);
            return $secondsSinceUpdate <= 5;
        }
        
        return false;
    }

    /**
     * Handle a job failure.
     */
    public function failed(MessageReceived $event, \Throwable $exception): void
    {
        Log::error('Falló el listener de auto-asignación', [
            'conversation_id' => $event->message->conversation_id,
            'error' => $exception->getMessage()
        ]);
    }
}