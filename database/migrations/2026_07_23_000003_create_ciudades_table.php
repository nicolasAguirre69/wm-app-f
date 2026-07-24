<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla `ciudades` (catálogo POR ISP).
     */
    public function up(): void
    {
        Schema::create('ciudades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')
                ->constrained('isps')   // FK → isps.id
                ->cascadeOnDelete();     // si se borra el ISP, se borran sus ciudades
            $table->string('nombre');
            $table->timestamps();
            $table->softDeletes();

            // Un mismo ISP no puede repetir el nombre de ciudad, pero dos
            // ISPs distintos sí pueden tener ambos "Bogotá".
            $table->unique(['isp_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ciudades');
    }
};
