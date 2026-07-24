<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla `barrios` (catálogo POR ISP, pertenece a una ciudad).
     */
    public function up(): void
    {
        Schema::create('barrios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')
                ->constrained('isps')
                ->cascadeOnDelete();
            // Cada barrio pertenece a una ciudad. restrictOnDelete: no se puede
            // borrar una ciudad si tiene barrios (evita huérfanos).
            $table->foreignId('ciudad_id')
                ->constrained('ciudades')
                ->restrictOnDelete();
            $table->string('nombre');
            $table->string('prefijo');
            $table->string('red');
            $table->timestamps();
            $table->softDeletes();

            // Un mismo ISP no repite el nombre de barrio dentro de la misma ciudad.
            $table->unique(['isp_id', 'ciudad_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barrios');
    }
};
