<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->string('estatus')->default('pendiente'); // pendiente | confirmada | cancelada | completada
            $table->string('nombre');
            $table->string('servicio');
            $table->decimal('precio_servicio', 10, 2)->nullable();
            $table->date('fecha');
            $table->time('hora');
            $table->string('numero_celular', 20);
            $table->string('estado');   // estado/provincia de la localización
            $table->string('ciudad');   // ciudad de la localización
            $table->string('direccion')->nullable(); // dirección específica
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
