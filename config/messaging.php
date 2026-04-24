<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Message Quota
    |--------------------------------------------------------------------------
    |
    | monthly_quota: 0 or less means unlimited.
    | warning_percent: threshold (1-99) to notify low quota.
    |
    */
    'monthly_quota' => (int) env('MESSAGE_MONTHLY_QUOTA', 1000),
    'warning_percent' => (int) env('MESSAGE_QUOTA_WARNING_PERCENT', 80),

    /*
    |--------------------------------------------------------------------------
    | Conversation Assignment
    |--------------------------------------------------------------------------
    |
    | Configuración para el sistema de asignación de conversaciones a asesores
    |
    */
    'max_conversations_per_agent' => (int) env('MAX_CONVERSATIONS_PER_AGENT', 10),
    
    // Departamento por defecto para nuevas conversaciones
    'default_department' => env('DEFAULT_DEPARTMENT', 'ventas'),
    
    // Auto-asignación activada
    'auto_assignment_enabled' => env('AUTO_ASSIGNMENT_ENABLED', true),
    
    // Tiempo en minutos para considerar una conversación inactiva
    'conversation_timeout_minutes' => (int) env('CONVERSATION_TIMEOUT_MINUTES', 30),
];
