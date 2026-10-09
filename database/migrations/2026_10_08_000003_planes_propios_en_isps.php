<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opción por ISP: "Administra planes propios".
 *
 * Una ISP cliente normalmente solo tiene clientes de TV (plan TV automático,
 * estados Activo/Retirado). Si se activa esta opción, la ISP vende sus propios
 * planes (Internet, Internet + TV...), elige el plan de cada cliente y maneja
 * también el estado Corte. Web Master solo le factura los servicios con TV.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('isps', function (Blueprint $table) {
            $table->boolean('planes_propios')->default(false)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('isps', function (Blueprint $table) {
            $table->dropColumn('planes_propios');
        });
    }
};
