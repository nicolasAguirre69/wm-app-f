<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina la columna `red` de barrios: ahora "red" es una entidad propia
     * (un barrio tiene muchas redes), no un campo de texto.
     */
    public function up(): void
    {
        Schema::table('barrios', function (Blueprint $table) {
            $table->dropColumn('red');
        });
    }

    /**
     * Revierte: vuelve a agregar la columna.
     */
    public function down(): void
    {
        Schema::table('barrios', function (Blueprint $table) {
            $table->string('red')->default('');
        });
    }
};
