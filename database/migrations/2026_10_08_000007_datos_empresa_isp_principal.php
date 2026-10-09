<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Datos de Web Master (ISP principal) para el encabezado del comprobante de
 * pago. Solo llena los campos que estén vacíos: si ya se editaron en ISPs,
 * no se tocan.
 */
return new class extends Migration
{
    private const DATOS = [
        'nit' => '901483116',
        'direccion' => 'Cl. 75 Sur #45a - 14',
        'telefono' => '3176683567',
    ];

    public function up(): void
    {
        foreach (self::DATOS as $campo => $valor) {
            DB::table('isps')
                ->where('tipo', 'principal')
                ->where(fn ($q) => $q->whereNull($campo)->orWhere($campo, ''))
                ->update([$campo => $valor]);
        }
    }

    public function down(): void
    {
        foreach (self::DATOS as $campo => $valor) {
            DB::table('isps')->where('tipo', 'principal')->where($campo, $valor)->update([$campo => null]);
        }
    }
};
