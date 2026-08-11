<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * en_estadisticas: si el estado aparece en el dashboard y los filtros.
     * Los "Mora …" antiguos se ocultan por defecto.
     */
    public function up(): void
    {
        Schema::table('estados_cliente', function (Blueprint $table) {
            $table->boolean('en_estadisticas')->default(true)->after('color');
        });

        DB::table('estados_cliente')->where('nombre', 'like', 'Mora%')->update(['en_estadisticas' => false]);
    }

    public function down(): void
    {
        Schema::table('estados_cliente', function (Blueprint $table) {
            $table->dropColumn('en_estadisticas');
        });
    }
};
