<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La opción "planes propios" (sí/no) pasa a ser una CATEGORÍA de ISP, para
 * poder agregar más tipos en el futuro:
 *   - solo_tv           -> clientes solo TV (lo de siempre)
 *   - gestion_completa  -> administra sus planes y clientes (ej. Net Bell)
 *
 * Las ISP que ya tenían planes_propios = 1 quedan como gestion_completa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('isps', function (Blueprint $table) {
            $table->string('categoria', 20)->default('solo_tv')->after('tipo');
        });

        if (Schema::hasColumn('isps', 'planes_propios')) {
            DB::table('isps')->where('planes_propios', true)->update(['categoria' => 'gestion_completa']);

            Schema::table('isps', function (Blueprint $table) {
                $table->dropColumn('planes_propios');
            });
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE isps ADD CONSTRAINT isps_categoria_check CHECK (categoria IN ('solo_tv','gestion_completa'))");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE isps DROP CONSTRAINT IF EXISTS isps_categoria_check');
        }

        Schema::table('isps', function (Blueprint $table) {
            $table->boolean('planes_propios')->default(false)->after('activo');
        });

        DB::table('isps')->where('categoria', 'gestion_completa')->update(['planes_propios' => true]);

        Schema::table('isps', function (Blueprint $table) {
            $table->dropColumn('categoria');
        });
    }
};
