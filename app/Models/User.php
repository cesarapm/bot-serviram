<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * Tipos de departamentos disponibles para asesores
     */
    public const DEPARTMENTS = [
        'ventas' => 'Ventas',
        'servicio_tecnico' => 'Servicio Técnico',
        'garantias' => 'Garantías',
        'refacciones' => 'Refacciones',
        'administracion' => 'Administración'
    ];

    /**
     * Boot del modelo - Asignar rol 'asesor' por defecto a nuevos usuarios
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($user) {
            // Asignar rol 'asesor' por defecto a todos los nuevos usuarios
            $user->assignRole('asesor');
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relación con conversaciones asignadas
     */
    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_to');
    }

    /**
     * Obtener el nombre legible del departamento
     */
    public function getDepartmentNameAttribute(): string
    {
        return self::DEPARTMENTS[$this->department] ?? 'Sin Departamento';
    }

    /**
     * Verificar si el asesor pertenece a un departamento específico
     */
    public function isFromDepartment(string $department): bool
    {
        return $this->department === $department;
    }

    /**
     * Scope para filtrar por departamento
     */
    public function scopeFromDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope para obtener solo asesores activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Verificar si el asesor está disponible para nuevas conversaciones
     */
    public function isAvailableForAssignment(): bool
    {
        return $this->is_active && !is_null($this->department);
    }
}
