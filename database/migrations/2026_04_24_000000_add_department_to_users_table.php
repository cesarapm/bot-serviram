<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Departamento/categoría del asesor
            $table->enum('department', [
                'ventas',
                'servicio_tecnico', 
                'garantias',
                'refacciones',
                'administracion'
            ])->nullable()->after('email');
            
            // Status del asesor (activo/inactivo)
            $table->boolean('is_active')->default(true)->after('department');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['department', 'is_active']);
        });
    }
};