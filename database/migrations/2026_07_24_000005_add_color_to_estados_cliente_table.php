<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega un color (hex) a cada estado de cliente, para colorear las filas.
     */
    public function up(): void
    {
        Schema::table('estados_cliente', function (Blueprint $table) {
            $table->string('color', 7)->default('#e5e7eb')->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('estados_cliente', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
