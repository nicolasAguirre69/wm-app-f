<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla `planes` (catálogo POR ISP).
     * Combina los catálogos globales tipos_plan y tipos_servicio.
     */
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')
                ->constrained('isps')
                ->cascadeOnDelete();
            // FK a catálogos GLOBALES. restrictOnDelete: no se puede borrar un
            // tipo si hay planes que lo usan.
            $table->foreignId('tipo_plan_id')
                ->constrained('tipos_plan')
                ->restrictOnDelete();
            $table->foreignId('tipo_servicio_id')
                ->constrained('tipos_servicio')
                ->restrictOnDelete();
            // Velocidad en Mbps. Nullable: un plan de solo TV no tiene Mbps.
            $table->unsignedInteger('cantidad')->nullable();
            // Precio. decimal(10,2), NUNCA float: los flotantes tienen errores
            // de redondeo inaceptables para dinero (ej. 0.1 + 0.2 != 0.3).
            $table->decimal('valor', 10, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
