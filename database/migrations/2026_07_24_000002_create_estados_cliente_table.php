<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla `estados_cliente` (catálogo POR ISP).
     * Ej: Activo, Suspendido, Retirado, Pendiente.
     */
    public function up(): void
    {
        Schema::create('estados_cliente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')
                ->constrained('isps')
                ->cascadeOnDelete();
            $table->string('nombre');
            $table->timestamps();
            $table->softDeletes();

            // Un mismo ISP no repite el nombre de estado.
            $table->unique(['isp_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estados_cliente');
    }
};
