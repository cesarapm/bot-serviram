<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
protected $fillable = ['contact_id', 'assigned_to', 'is_human', 'status', 'department'];

    protected $casts = [
        'is_human' => 'boolean',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Obtener el departamento del asesor asignado
     */
    public function getAssignedDepartmentAttribute(): ?string
    {
        // Priorizar el departamento directo de la conversación
        return $this->department ?? $this->assignedAgent?->department;
    }

    /**
     * Obtener el nombre del departamento
     */
    public function getAssignedDepartmentNameAttribute(): string
    {
        $department = $this->assigned_department;
        return $department ? (User::DEPARTMENTS[$department] ?? 'Departamento Desconocido') : 'Sin Asignar';
    }

    /**
     * Verificar si la conversación está asignada a un departamento específico
     */
    public function isAssignedToDepartment(string $department): bool
    {
        return $this->assigned_department === $department;
    }

    /**
     * Scope para filtrar por departamento
     */
    public function scopeByDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope para conversaciones sin asignar a departamento
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('department');
    }

    /**
     * Scope para conversaciones activas
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Asignar conversación a departamento
     */
    public function assignToDepartment(string $department, ?User $specificAgent = null): void
    {
        $this->update([
            'department' => $department,
            'assigned_to' => $specificAgent?->id,
            'is_human' => true,
            'status' => 'active'
        ]);
    }

    /**
     * Verificar si pueden ver esta conversación usuarios de un departamento
     */
    public function canBeViewedByDepartment(string $userDepartment): bool
    {
        return $this->assigned_department === $userDepartment;
    }
}
