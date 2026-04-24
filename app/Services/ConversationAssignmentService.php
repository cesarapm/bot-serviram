<?php

namespace App\Services;

use App\Models\User;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Collection;

class ConversationAssignmentService
{
    /**
     * Asignar conversación a departamento
     */
    public function assignToDepartment(Conversation $conversation, string $department): bool
    {
        // Verificar que el departamento existe
        if (!array_key_exists($department, User::DEPARTMENTS)) {
            return false;
        }

        // Verificar que hay asesores activos en el departamento
        $activeAgents = $this->getAgentsByDepartment($department);
        if ($activeAgents->isEmpty()) {
            return false;
        }

        // Asignar a departamento (sin asesor específico por ahora)
        $conversation->assignToDepartment($department);
        
        return true;
    }

    /**
     * Asignar conversación a asesor disponible del departamento especificado
     * DEPRECATED: Usar assignToDepartment() en su lugar
     */
    public function assignToAvailableAgent(Conversation $conversation, string $department = null): bool
    {
        return $this->assignToDepartment($conversation, $department ?? 'ventas');
    }

    /**
     * Buscar asesor disponible en un departamento específico
     */
    public function findAvailableAgent(string $department): ?User
    {
        return User::active()
            ->fromDepartment($department)
            ->whereHas('assignedConversations', function($query) {
                $query->where('status', 'active');
            }, '<', $this->getMaxConversationsPerAgent())
            ->first();
    }

    /**
     * Obtener todos los asesores por departamento
     */
    public function getAgentsByDepartment(string $department): Collection
    {
        return User::active()
            ->fromDepartment($department)
            ->get();
    }

    /**
     * Reasignar conversación a otro departamento
     */
    public function reassignToDepartment(Conversation $conversation, string $newDepartment): bool
    {
        return $this->assignToDepartment($conversation, $newDepartment);
    }

    /**
     * Obtener estadísticas de carga de trabajo por departamento
     */
    public function getDepartmentWorkload(): array
    {
        $stats = [];
        
        foreach (User::DEPARTMENTS as $key => $name) {
            $agents = $this->getAgentsByDepartment($key);
            $totalConversations = Conversation::byDepartment($key)
                ->where('status', 'active')
                ->count();

            $stats[$key] = [
                'name' => $name,
                'agents_count' => $agents->count(),
                'active_conversations' => $totalConversations,
                'avg_conversations_per_agent' => $agents->count() > 0 ? round($totalConversations / $agents->count(), 1) : 0
            ];
        }

        return $stats;
    }

    /**
     * Obtener el máximo de conversaciones por asesor (configurable)
     */
    private function getMaxConversationsPerAgent(): int
    {
        return config('messaging.max_conversations_per_agent', 10);
    }

    /**
     * Determinar departamento según tipo de consulta (lógica de negocio personalizable)
     */
    public function determineDepartmentByQuery(string $query): string
    {
        $query = strtolower($query);

        // Palabras clave para cada departamento
        $keywords = [
            'ventas' => ['precio', 'comprar', 'venta', 'cotizar', 'costo', 'promocion', 'descuento'],
            'servicio_tecnico' => ['problema', 'error', 'falla', 'soporte', 'tecnico', 'instalacion', 'configurar'],
            'garantias' => ['garantia', 'devolucion', 'cambio', 'defecto', 'reclamo', 'warranty'],
            'refacciones' => ['repuesto', 'pieza', 'refaccion', 'componente', 'spare', 'parte'],
            'administracion' => ['factura', 'pago', 'cuenta', 'administrativo', 'billing', 'contabilidad']
        ];

        foreach ($keywords as $department => $words) {
            foreach ($words as $word) {
                if (strpos($query, $word) !== false) {
                    return $department;
                }
            }
        }

        // Por defecto asignar a ventas
        return 'ventas';
    }
}