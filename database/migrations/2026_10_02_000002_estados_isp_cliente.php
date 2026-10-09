<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Norma: en las ISP de tipo "cliente" un cliente solo puede estar Activo o
 * Retirado. La ISP principal no se toca.
 *
 * Para cada ISP cliente:
 *   1. Garantiza que existan los estados "Activo" y "Retirado".
 *   2. Pasa a "Retirado" a cualquier cliente que esté en otro estado
 *      (Corte, Suspendido...). "Activo" se respeta tal cual.
 *   3. Elimina los demás estados de esa ISP (ya sin clientes).
 *
 * Es idempotente: si ya está todo en regla, no cambia nada.
 */
return new class extends Migration
{
    private const PERMITIDOS = ['Activo' => '#22c55e', 'Retirado' => '#ef4444'];

    public function up(): void
    {
        $ispsCliente = DB::table('isps')->where('tipo', 'cliente')->pluck('id');

        foreach ($ispsCliente as $ispId) {
            DB::transaction(function () use ($ispId) {
                $ids = [];

                foreach (self::PERMITIDOS as $nombre => $color) {
                    $estado = DB::table('estados_cliente')
                        ->where('isp_id', $ispId)->where('nombre', $nombre)->first();

                    if ($estado) {
                        // Si estaba borrado lógicamente, se recupera.
                        if ($estado->deleted_at !== null) {
                            DB::table('estados_cliente')->where('id', $estado->id)
                                ->update(['deleted_at' => null, 'updated_at' => now()]);
                        }
                        $ids[$nombre] = $estado->id;
                    } else {
                        $ids[$nombre] = DB::table('estados_cliente')->insertGetId([
                            'isp_id' => $ispId,
                            'nombre' => $nombre,
                            'color' => $color,
                            'en_estadisticas' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                // Clientes (incluidos los borrados lógicamente) en un estado no permitido.
                DB::table('clientes')
                    ->where('isp_id', $ispId)
                    ->whereNotIn('estado_id', array_values($ids))
                    ->update(['estado_id' => $ids['Retirado'], 'updated_at' => now()]);

                // Los demás estados de esta ISP ya no tienen clientes: se eliminan.
                DB::table('estados_cliente')
                    ->where('isp_id', $ispId)
                    ->whereNotIn('id', array_values($ids))
                    ->delete();
            });
        }
    }

    /**
     * Solo recrea los estados Suspendido y Corte en las ISP cliente; no puede
     * saber en qué estado estaba antes cada cliente.
     */
    public function down(): void
    {
        $ispsCliente = DB::table('isps')->where('tipo', 'cliente')->pluck('id');

        foreach ($ispsCliente as $ispId) {
            foreach (['Suspendido' => '#3b82f6', 'Corte' => '#f59e0b'] as $nombre => $color) {
                $existe = DB::table('estados_cliente')
                    ->where('isp_id', $ispId)->where('nombre', $nombre)->exists();

                if (! $existe) {
                    DB::table('estados_cliente')->insert([
                        'isp_id' => $ispId,
                        'nombre' => $nombre,
                        'color' => $color,
                        'en_estadisticas' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
};
