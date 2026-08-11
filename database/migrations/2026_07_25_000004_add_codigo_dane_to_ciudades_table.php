<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * codigo_dane: código del municipio (DANE) usado en la facturación.
     */
    public function up(): void
    {
        Schema::table('ciudades', function (Blueprint $table) {
            $table->string('codigo_dane', 10)->nullable()->after('nombre');
        });

        DB::table('ciudades')->where('nombre', 'Bogotá')->update(['codigo_dane' => '11001']);
        DB::table('ciudades')->where('nombre', 'Soacha')->update(['codigo_dane' => '25754']);
    }

    public function down(): void
    {
        Schema::table('ciudades', function (Blueprint $table) {
            $table->dropColumn('codigo_dane');
        });
    }
};
