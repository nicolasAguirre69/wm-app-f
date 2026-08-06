<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vuelve opcionales los campos que la data real no siempre tiene:
     * correo, segundo apellido, fecha de instalación y día de corte.
     * (Requiere doctrine/dbal en versiones antiguas; Laravel 12 lo maneja nativo.)
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('correo')->nullable()->change();
            $table->string('segundo_apellido')->nullable()->change();
            $table->date('fecha_instalacion')->nullable()->change();
            $table->unsignedTinyInteger('dia_corte')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('correo')->nullable(false)->change();
            $table->string('segundo_apellido')->nullable(false)->change();
            $table->date('fecha_instalacion')->nullable(false)->change();
            $table->unsignedTinyInteger('dia_corte')->nullable(false)->change();
        });
    }
};
