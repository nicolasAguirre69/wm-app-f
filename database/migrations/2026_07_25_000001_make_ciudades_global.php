<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convierte `ciudades` en un catálogo GLOBAL (sin isp_id):
     * fusiona las ciudades duplicadas (misma nombre en varias ISPs) en una
     * sola, repuntando barrios y clientes a la ciudad que sobrevive.
     */
    public function up(): void
    {
        // 1. Repuntar barrios a la ciudad canónica (el menor id por nombre).
        DB::statement('
            UPDATE barrios b
            JOIN ciudades c ON b.ciudad_id = c.id
            JOIN (SELECT nombre, MIN(id) AS keep_id FROM ciudades GROUP BY nombre) k
                ON c.nombre = k.nombre
            SET b.ciudad_id = k.keep_id
        ');

        // 2. Repuntar clientes de igual forma.
        DB::statement('
            UPDATE clientes cl
            JOIN ciudades c ON cl.ciudad_id = c.id
            JOIN (SELECT nombre, MIN(id) AS keep_id FROM ciudades GROUP BY nombre) k
                ON c.nombre = k.nombre
            SET cl.ciudad_id = k.keep_id
        ');

        // 3. Borrar las ciudades duplicadas (ya nadie las referencia).
        DB::statement('
            DELETE c FROM ciudades c
            JOIN (SELECT nombre, MIN(id) AS keep_id FROM ciudades GROUP BY nombre) k
                ON c.nombre = k.nombre
            WHERE c.id <> k.keep_id
        ');

        // 4. Quitar el isp_id y su índice/llave; hacer el nombre único global.
        Schema::table('ciudades', function (Blueprint $table) {
            $table->dropForeign(['isp_id']);
            $table->dropUnique(['isp_id', 'nombre']);
            $table->dropColumn('isp_id');
            $table->unique('nombre');
        });
    }

    public function down(): void
    {
        // Reversa simple: vuelve a agregar isp_id (todas al ISP Principal).
        Schema::table('ciudades', function (Blueprint $table) {
            $table->dropUnique(['nombre']);
            $table->foreignId('isp_id')->nullable()->after('id')->constrained('isps')->cascadeOnDelete();
        });

        $principal = DB::table('isps')->where('tipo', 'principal')->value('id');
        DB::table('ciudades')->update(['isp_id' => $principal]);
    }
};
