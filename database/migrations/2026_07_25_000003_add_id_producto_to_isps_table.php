<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * id_producto: identificador del producto en el sistema de facturación
     * externo. Uno por ISP. Se usa en la exportación de facturación.
     */
    public function up(): void
    {
        Schema::table('isps', function (Blueprint $table) {
            $table->unsignedInteger('id_producto')->nullable()->after('activo');
        });

        // Valores conocidos por nombre de ISP (no-op si no existen aún).
        $mapa = [
            'Web Master Colombia' => 21,
            'Inttel Go' => 22,
            'Telecomunicaciones Avanzadas del Sur' => 23,
            'Net Bell' => 24,
            'Nube Net' => 25,
        ];
        foreach ($mapa as $nombre => $id) {
            DB::table('isps')->where('nombre', $nombre)->update(['id_producto' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('isps', function (Blueprint $table) {
            $table->dropColumn('id_producto');
        });
    }
};
