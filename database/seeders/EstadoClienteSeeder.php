<?php

namespace Database\Seeders;

use App\Models\EstadoCliente;
use App\Models\Isp;
use Illuminate\Database\Seeder;

/**
 * Estados de cliente iniciales del ISP Principal.
 */
class EstadoClienteSeeder extends Seeder
{
    public function run(): void
    {
        $ispPrincipal = Isp::where('tipo', 'principal')->first();

        if (! $ispPrincipal) {
            return;
        }

        foreach (['Activo', 'Suspendido', 'Retirado', 'Pendiente'] as $nombre) {
            EstadoCliente::firstOrCreate([
                'isp_id' => $ispPrincipal->id,
                'nombre' => $nombre,
            ]);
        }
    }
}
