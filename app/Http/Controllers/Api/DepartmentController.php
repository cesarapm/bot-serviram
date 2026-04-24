<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Conversation;
use App\Services\ConversationAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    protected ConversationAssignmentService $assignmentService;

    public function __construct(ConversationAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * Obtener lista de departamentos disponibles
     */
    public function getDepartments(): JsonResponse
    {
        return response()->json([
            'departments' => User::DEPARTMENTS
        ]);
    }

    /**
     * Obtener asesores por departamento
     */
    public function getAgentsByDepartment(string $department): JsonResponse
    {
        if (!array_key_exists($department, User::DEPARTMENTS)) {
            return response()->json(['error' => 'Departamento no válido'], 400);
        }

        $agents = $this->assignmentService->getAgentsByDepartment($department);
        
        return response()->json([
            'department' => User::DEPARTMENTS[$department],
            'agents' => $agents->map(function($agent) {
                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'email' => $agent->email,
                    'is_active' => $agent->is_active,
                    'active_conversations' => $agent->assignedConversations()->where('status', 'active')->count(),
                ];
            })
        ]);
    }

    /**
     * Obtener estadísticas de carga de trabajo
     */
    public function getDepartmentStats(): JsonResponse
    {
        $stats = $this->assignmentService->getDepartmentWorkload();
        
        return response()->json([
            'workload_stats' => $stats,
            'total_conversations' => Conversation::active()->count(),
            'unassigned_conversations' => Conversation::unassigned()->active()->count(),
            'total_agents' => User::active()->whereNotNull('department')->count(),
        ]);
    }

    /**
     * Asignar conversación manualmente
     */
    public function assignConversation(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'required|exists:conversations,id',
            'agent_id' => 'nullable|exists:users,id',
            'department' => ['nullable', Rule::in(array_keys(User::DEPARTMENTS))],
        ]);

        $conversation = Conversation::find($request->conversation_id);
        
        if ($request->agent_id) {
            // Asignar a asesor específico
            $agent = User::find($request->agent_id);
            if (!$agent->isAvailableForAssignment()) {
                return response()->json(['error' => 'El asesor no está disponible'], 400);
            }
            
            $conversation->reassignTo($agent);
            
            return response()->json([
                'message' => 'Conversación asignada exitosamente',
                'assigned_to' => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'department' => $agent->department_name,
                ]
            ]);
        } elseif ($request->department) {
            // Asignar a departamento
            $success = $this->assignmentService->assignToAvailableAgent($conversation, $request->department);
            
            if ($success) {
                $conversation->refresh();
                return response()->json([
                    'message' => 'Conversación asignada al departamento exitosamente',
                    'assigned_to' => [
                        'id' => $conversation->assignedAgent->id,
                        'name' => $conversation->assignedAgent->name,
                        'department' => $conversation->assignedAgent->department_name,
                    ]
                ]);
            } else {
                return response()->json(['error' => 'No hay asesores disponibles en el departamento'], 400);
            }
        } else {
            return response()->json(['error' => 'Se requiere agent_id o department'], 400);
        }
    }

    /**
     * Actualizar asesor
     */
    public function updateAgent(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'department' => ['sometimes', Rule::in(array_keys(User::DEPARTMENTS))],
            'is_active' => 'sometimes|boolean',
        ]);

        $user->update($request->only(['name', 'email', 'department', 'is_active']));

        return response()->json([
            'message' => 'Asesor actualizado exitosamente',
            'agent' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department,
                'department_name' => $user->department_name,
                'is_active' => $user->is_active,
            ]
        ]);
    }

    /**
     * Obtener conversaciones sin asignar
     */
    public function getUnassignedConversations(): JsonResponse
    {
        $conversations = Conversation::unassigned()
            ->active()
            ->with(['contact', 'lastMessage'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'unassigned_conversations' => $conversations->map(function($conversation) {
                return [
                    'id' => $conversation->id,
                    'contact' => [
                        'id' => $conversation->contact->id,
                        'name' => $conversation->contact->name,
                        'phone' => $conversation->contact->phone,
                    ],
                    'last_message' => $conversation->lastMessage ? [
                        'content' => $conversation->lastMessage->content,
                        'created_at' => $conversation->lastMessage->created_at,
                    ] : null,
                    'suggested_department' => $conversation->lastMessage ? 
                        $this->assignmentService->determineDepartmentByQuery($conversation->lastMessage->content) : 'ventas',
                    'created_at' => $conversation->created_at,
                ];
            })
        ]);
    }

    /**
     * Auto-asignar todas las conversaciones sin asignar
     */
    public function autoAssignAll(): JsonResponse
    {
        $unassigned = Conversation::unassigned()->active()->get();
        $assigned = 0;

        foreach ($unassigned as $conversation) {
            $lastMessage = $conversation->lastMessage;
            $department = $lastMessage ? 
                $this->assignmentService->determineDepartmentByQuery($lastMessage->content) : 'ventas';
            
            if ($this->assignmentService->assignToAvailableAgent($conversation, $department)) {
                $assigned++;
            }
        }

        return response()->json([
            'message' => "Se asignaron $assigned de {$unassigned->count()} conversaciones",
            'assigned_count' => $assigned,
            'total_unassigned' => $unassigned->count(),
        ]);
    }
}