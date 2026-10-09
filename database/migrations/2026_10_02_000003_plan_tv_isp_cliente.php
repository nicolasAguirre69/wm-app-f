<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cada ISP cliente debe tener su plan de TV por defecto (Hogar - TV, valor 0),
 * que se asigna automáticamente al crear sus clientes. Crea el plan en las
 * ISP cliente que todavía no lo tienen. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tvId = DB::table('tipos_servicio')->where('nombre', 'TV')->value('id');
        $hogarId = DB::table('tipos_plan')->where('nombre', 'Hogar')->value('id')
            ?? DB::table('tipos_plan')->orderBy('id')->value('id');

        if (! $tvId || ! $hogarId) {
            return; // Catálogos aún vacíos (instalación nueva): lo hará el IspObserver.
        }

        $ispsCliente = DB::table('isps')->where('tipo', 'cliente')->pluck('id');

        foreach ($ispsCliente as $ispId) {
            $tiene = DB::table('planes')
                ->where('isp_id', $ispId)
                ->where('tipo_servicio_id', $tvId)
                ->whereNull('deleted_at')
                ->exists();

            if (! $tiene) {
                DB::table('planes')->insert([
                    'isp_id' => $ispId,
                    'tipo_plan_id' => $hogarId,
                    'tipo_servicio_id' => $tvId,
                    'cantidad' => null,
                    'valor' => 0,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No se borran planes: podrían tener clientes asignados.
    }
};
