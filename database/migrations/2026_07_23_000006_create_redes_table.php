<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla `redes` (POR ISP, pertenece a un barrio).
     */
    public function up(): void
    {
        Schema::create('redes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')
                ->constrained('isps')
                ->cascadeOnDelete();
            // Cada red pertenece a un barrio. No se puede borrar un barrio
            // que tenga redes (restrictOnDelete).
            $table->foreignId('barrio_id')
                ->constrained('barrios')
                ->restrictOnDelete();
            // Número de red (1 a 16). Entero para que ordene correctamente.
            // El nombre completo "1USM" se calcula con el prefijo del barrio.
            $table->unsignedTinyInteger('numero');
            $table->timestamps();
            $table->softDeletes();

            // Un mismo ISP no repite el número de red dentro del mismo barrio.
            $table->unique(['isp_id', 'barrio_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redes');
    }
};
