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
        // Ciudades es un catálogo GLOBAL (sin isp_id).
        foreach (['Bogotá', 'Soacha'] as $nombre) {
            Ciudad::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
