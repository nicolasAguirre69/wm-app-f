<?php

namespace Database\Seeders;

use App\Models\Barrio;
use App\Models\Isp;
use App\Models\Red;
use Illuminate\Database\Seeder;

/**
 * Redes iniciales de ejemplo por barrio del ISP Principal.
 * Genera 2 redes por barrio (número 1 y 2). El nombre completo (1USM, 2USM)
 * lo arma el accessor del modelo con el prefijo del barrio.
 */
class RedSeeder extends Seeder
{
    public function run(): void
    {
        $ispPrincipal = Isp::where('tipo', 'principal')->first();

        if (! $ispPrincipal) {
            return;
        }

        $barrios = Barrio::where('isp_id', $ispPrincipal->id)->get();

        foreach ($barrios as $barrio) {
            for ($i = 1; $i <= 2; $i++) {
                Red::firstOrCreate([
                    'isp_id' => $ispPrincipal->id,
                    'barrio_id' => $barrio->id,
                    'numero' => (string) $i,
                ]);
            }
        }
    }
}
