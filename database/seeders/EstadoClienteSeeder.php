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

        $estados = [
            'Activo' => '#22c55e',      // verde
            'Suspendido' => '#f59e0b',  // ámbar
            'Retirado' => '#ef4444',    // rojo
            'Pendiente' => '#3b82f6',   // azul
        ];

        foreach ($estados as $nombre => $color) {
            EstadoCliente::updateOrCreate(
                ['isp_id' => $ispPrincipal->id, 'nombre' => $nombre],
                ['color' => $color],
            );
        }
    }
}
