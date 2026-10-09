<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servicios de la ISP principal que llegan al cliente por un puerto alquilado
 * a una ISP externa. Solo aplica a la ISP principal (lo controla la app);
 * en las ISP cliente siempre queda en false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('puerto_alquilado')->default(false)->after('facturable')
                ->comment('Servicio sobre un puerto alquilado a una ISP externa');
            $table->index(['isp_id', 'puerto_alquilado']);
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['isp_id', 'puerto_alquilado']);
            $table->dropColumn('puerto_alquilado');
        });
    }
};
