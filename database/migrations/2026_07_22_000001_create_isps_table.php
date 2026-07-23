<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta la migración: crea la tabla `isps`.
     */
    public function up(): void
    {
        Schema::create('isps', function (Blueprint $table) {
            $table->id();                          // PK autoincremental (bigint)
            $table->string('nombre');              // Nombre del ISP
            $table->string('tipo')->default('cliente'); // 'principal' | 'cliente' (validado por Enum en el modelo)
            $table->boolean('activo')->default(true);   // Si el ISP está activo en la plataforma
            $table->timestamps();                  // created_at y updated_at
            $table->softDeletes();                 // Columna deleted_at para el borrado lógico
        });
    }

    /**
     * Revierte la migración: elimina la tabla `isps`.
     */
    public function down(): void
    {
        Schema::dropIfExists('isps');
    }
};
