<?php

namespace Database\Seeders;

use App\Models\TipoPlan;
use Illuminate\Database\Seeder;

class TipoPlanSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = ['Hogar', 'Comercial', 'Corporativo', 'Dedicado'];

        foreach ($tipos as $nombre) {
            TipoPlan::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
