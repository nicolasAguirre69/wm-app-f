<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega los campos de multi-tenancy y rol global a la tabla `users`.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // A qué ISP pertenece el usuario. Nullable: el Super Admin es un
            // caso especial. La regla "todo usuario normal tiene ISP" se
            // valida en la capa de aplicación (Form Requests).
            $table->foreignId('isp_id')
                ->nullable()
                ->after('id')
                ->constrained('isps')   // FK → isps.id (integridad referencial)
                ->nullOnDelete();        // si se borra el ISP, isp_id queda null

            // Marca al usuario con acceso global a toda la plataforma.
            $table->boolean('is_super_admin')
                ->default(false)
                ->after('password');

            // Estado del usuario (activo/inactivo). Bloquea acceso sin borrar.
            $table->boolean('activo')
                ->default(true)
                ->after('is_super_admin');

            // Borrado lógico (soft delete) también para usuarios.
            $table->softDeletes();
        });
    }

    /**
     * Revierte los cambios.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['isp_id']);
            $table->dropColumn(['isp_id', 'is_super_admin', 'activo', 'deleted_at']);
        });
    }
};
