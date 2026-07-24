<?php

namespace App\Observers;

use App\Models\Barrio;
use App\Models\Red;

/**
 * Observer del modelo Barrio.
 *
 * Al crear un barrio, genera automáticamente sus 16 redes (números 1 a 16).
 * El nombre completo de cada red (1USM, 2USM...) lo arma el accessor con el
 * prefijo del barrio.
 */
class BarrioObserver
{
    private const TOTAL_REDES = 16;

    public function created(Barrio $barrio): void
    {
        for ($numero = 1; $numero <= self::TOTAL_REDES; $numero++) {
            Red::firstOrCreate([
                // Tomamos el isp_id del barrio para que funcione también en
                // seeders (donde no hay usuario autenticado).
                'isp_id' => $barrio->isp_id,
                'barrio_id' => $barrio->id,
                'numero' => (string) $numero,
            ]);
        }
    }
}
