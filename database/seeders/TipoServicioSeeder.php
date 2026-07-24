<?php

namespace Database\Seeders;

use App\Models\TipoServicio;
use Illuminate\Database\Seeder;

class TipoServicioSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            'Internet',
            'Internet + TV',
            'Internet + TV + Tel',
            'Internet + Tel',
            'TV',
        ];

        foreach ($tipos as $nombre) {
            TipoServicio::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
