<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla `tipos_plan` (catálogo GLOBAL, sin isp_id).
     * Valores: Hogar, Comercial, Corporativo, Dedicado.
     */
    public function up(): void
    {
        Schema::create('tipos_plan', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();  // Global: nombre único en todo el sistema
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_plan');
    }
};
