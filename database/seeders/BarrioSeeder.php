<?php

namespace Database\Seeders;

use App\Models\Barrio;
use App\Models\Ciudad;
use App\Models\Isp;
use Illuminate\Database\Seeder;

/**
 * Datos iniciales de barrios del ISP Principal.
 *
 * Bogotá  -> Usme, Ciudad Bolívar
 * Soacha  -> Ducales
 *
 * Los valores de prefijo son de ejemplo; edítalos desde la interfaz.
 * Las redes de cada barrio se siembran en RedSeeder.
 * (En un seeder no hay usuario autenticado, así que el Global Scope no
 * filtra y debemos indicar el isp_id explícitamente.)
 */
class BarrioSeeder extends Seeder
{
    public function run(): void
    {
        $ispPrincipal = Isp::where('tipo', 'principal')->first();

        if (! $ispPrincipal) {
            return; // Sin ISP Principal no hay nada que sembrar.
        }

        $barriosPorCiudad = [
            'Bogotá' => [
                ['nombre' => 'Usme', 'prefijo' => 'USM'],
                ['nombre' => 'Ciudad Bolívar', 'prefijo' => 'CBO'],
            ],
            'Soacha' => [
                ['nombre' => 'Ducales', 'prefijo' => 'DUC'],
            ],
        ];

        foreach ($barriosPorCiudad as $nombreCiudad => $barrios) {
            $ciudad = Ciudad::where('isp_id', $ispPrincipal->id)
                ->where('nombre', $nombreCiudad)
                ->first();

            if (! $ciudad) {
                continue; // Si la ciudad no existe, saltamos sus barrios.
            }

            foreach ($barrios as $barrio) {
                Barrio::firstOrCreate(
                    [
                        'isp_id' => $ispPrincipal->id,
                        'ciudad_id' => $ciudad->id,
                        'nombre' => $barrio['nombre'],
                    ],
                    [
                        'prefijo' => $barrio['prefijo'],
                    ],
                );
            }
        }
    }
}
