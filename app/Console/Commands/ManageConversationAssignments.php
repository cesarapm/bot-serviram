<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ConversationAssignmentService;
use App\Models\Conversation;
use App\Models\User;

class ManageConversationAssignments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conversations:manage 
                           {action : assign|reassign|stats|list-unassigned}
                           {--department= : Departamento específico}
                           {--conversation= : ID de conversación específica}
                           {--agent= : ID de asesor específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gestionar asignaciones de conversaciones a asesores por departamento';

    protected ConversationAssignmentService $assignmentService;

    public function __construct(ConversationAssignmentService $assignmentService)
    {
        parent::__construct();
        $this->assignmentService = $assignmentService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'assign':
                return $this->handleAssignment();
            case 'reassign':
                return $this->handleReassignment();
            case 'stats':
                return $this->showStats();
            case 'list-unassigned':
                return $this->listUnassigned();
            default:
                $this->error("Acción no válida: $action");
                return 1;
        }
    }

    private function handleAssignment(): int
    {
        $department = $this->option('department');
        $conversationId = $this->option('conversation');

        if ($conversationId) {
            // Asignar conversación específica
            $conversation = Conversation::find($conversationId);
            if (!$conversation) {
                $this->error("Conversación $conversationId no encontrada");
                return 1;
            }

            $success = $this->assignmentService->assignToAvailableAgent($conversation, $department);
            
            if ($success) {
                $this->info("✅ Conversación $conversationId asignada exitosamente");
                return 0;
            } else {
                $this->error("❌ No se pudo asignar la conversación $conversationId");
                return 1;
            }
        } else {
            // Asignar todas las conversaciones sin asignar
            $unassigned = Conversation::unassigned()->active()->get();
            $assigned = 0;

            foreach ($unassigned as $conversation) {
                if ($this->assignmentService->assignToAvailableAgent($conversation, $department)) {
                    $assigned++;
                }
            }

            $this->info("✅ Se asignaron $assigned de {$unassigned->count()} conversaciones sin asignar");
            return 0;
        }
    }

    private function handleReassignment(): int
    {
        $conversationId = $this->option('conversation');
        $department = $this->option('department');
        $agentId = $this->option('agent');

        if (!$conversationId) {
            $this->error('Se requiere el ID de conversación para reasignar');
            return 1;
        }

        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            $this->error("Conversación $conversationId no encontrada");
            return 1;
        }

        if ($agentId) {
            // Reasignar a asesor específico
            $agent = User::find($agentId);
            if (!$agent) {
                $this->error("Asesor $agentId no encontrado");
                return 1;
            }

            $conversation->reassignTo($agent);
            $this->info("✅ Conversación reasignada a {$agent->name} ({$agent->department_name})");
            return 0;
        } elseif ($department) {
            // Reasignar a departamento
            $success = $this->assignmentService->reassignToDepartment($conversation, $department);
            
            if ($success) {
                $this->info("✅ Conversación reasignada al departamento de " . User::DEPARTMENTS[$department]);
                return 0;
            } else {
                $this->error("❌ No hay asesores disponibles en el departamento de " . User::DEPARTMENTS[$department]);
                return 1;
            }
        } else {
            $this->error('Se requiere --department o --agent para reasignar');
            return 1;
        }
    }

    private function showStats(): int
    {
        $stats = $this->assignmentService->getDepartmentWorkload();

        $this->info('📊 Estadísticas de Carga de Trabajo por Departamento');
        $this->line('');

        $headers = ['Departamento', 'Asesores', 'Conv. Activas', 'Promedio/Asesor'];
        $rows = [];

        foreach ($stats as $key => $data) {
            $rows[] = [
                $data['name'],
                $data['agents_count'],
                $data['active_conversations'],
                $data['avg_conversations_per_agent']
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }

    private function listUnassigned(): int
    {
        $unassigned = Conversation::unassigned()
            ->active()
            ->with(['contact', 'lastMessage'])
            ->get();

        if ($unassigned->isEmpty()) {
            $this->info('✅ No hay conversaciones sin asignar');
            return 0;
        }

        $this->info("📋 Conversaciones sin asignar ({$unassigned->count()})");
        $this->line('');

        $headers = ['ID', 'Contacto', 'Último Mensaje', 'Creada'];
        $rows = [];

        foreach ($unassigned as $conversation) {
            $rows[] = [
                $conversation->id,
                $conversation->contact->name ?? 'Sin nombre',
                $conversation->lastMessage?->content ? 
                    \Str::limit($conversation->lastMessage->content, 50) : 'Sin mensajes',
                $conversation->created_at->diffForHumans()
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}