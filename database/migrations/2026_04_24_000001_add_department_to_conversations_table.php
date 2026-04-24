<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Agregar departamento a la conversación
            $table->enum('department', [
                'ventas',
                'servicio_tecnico',
                'garantias', 
                'refacciones',
                'administracion'
            ])->nullable()->after('status');
            
            // El assigned_to ahora es opcional (solo para casos especiales)
            $table->foreignId('assigned_to')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('department');
        });
    }
};