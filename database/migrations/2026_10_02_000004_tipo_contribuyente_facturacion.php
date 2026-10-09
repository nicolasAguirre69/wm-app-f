<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El tipo de contribuyente pasa a los 4 valores del sistema de facturación
 * y se deduce del tipo de identificación de cada titular:
 *   CC, TI            -> regimen_comun       (Regimen Comun)
 *   NIT               -> gran_contribuyente  (Gran Contribuyente)
 *   CE, PA, PPT, PEP  -> tercero_exterior    (Tercero Exterior)
 * (regimen_simplificado existe en el catálogo pero ninguna regla lo asigna.)
 */
return new class extends Migration
{
    private const NUEVOS = "'regimen_comun','regimen_simplificado','gran_contribuyente','tercero_exterior'";

    private const ANTERIORES = "'natural','regimen_comun','juridica','gran_contribuyente','regimen_simple','no_responsable_iva'";

    public function up(): void
    {
        $esMysql = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        if ($esMysql) {
            DB::statement('ALTER TABLE titulares DROP CONSTRAINT IF EXISTS titulares_tipo_contribuyente_check');
        }

        DB::table('titulares')->update([
            'tipo_contribuyente' => DB::raw("CASE
                WHEN tipo_identificacion = 'NIT' THEN 'gran_contribuyente'
                WHEN tipo_identificacion IN ('CE','PA','PPT','PEP') THEN 'tercero_exterior'
                ELSE 'regimen_comun'
            END"),
        ]);

        if ($esMysql) {
            DB::statement('ALTER TABLE titulares ADD CONSTRAINT titulares_tipo_contribuyente_check
                CHECK (tipo_contribuyente IN ('.self::NUEVOS.'))');
        }
    }

    /**
     * Vuelve al catálogo anterior (los valores originales de cada titular no
     * se pueden recuperar: queda regimen_comun / gran_contribuyente).
     */
    public function down(): void
    {
        $esMysql = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        if ($esMysql) {
            DB::statement('ALTER TABLE titulares DROP CONSTRAINT IF EXISTS titulares_tipo_contribuyente_check');
        }

        DB::table('titulares')
            ->whereIn('tipo_contribuyente', ['regimen_simplificado', 'tercero_exterior'])
            ->update(['tipo_contribuyente' => 'regimen_comun']);

        if ($esMysql) {
            DB::statement('ALTER TABLE titulares ADD CONSTRAINT titulares_tipo_contribuyente_check
                CHECK (tipo_contribuyente IN ('.self::ANTERIORES.'))');
        }
    }
};
