<?php

namespace Database\Seeders;

use App\Models\Ciudad;
use App\Models\Isp;
use Illuminate\Database\Seeder;

/**
 * Ciudades iniciales del ISP Principal (Bogotá, Soacha).
 */
class CiudadSeeder extends Seeder
{
    public function run(): void
    {
        $ispPrincipal = Isp::where('tipo', 'principal')->first();

        if (! $ispPrincipal) {
            return;
        }

        foreach (['Bogotá', 'Soacha'] as $nombre) {
            Ciudad::firstOrCreate([
                'isp_id' => $ispPrincipal->id,
                'nombre' => $nombre,
            ]);
        }
    }
}
