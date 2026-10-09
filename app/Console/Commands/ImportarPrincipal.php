<?php

namespace App\Console\Commands;

use App\Models\Barrio;
use App\Models\Ciudad;
use App\Models\Cliente;
use App\Models\EstadoCliente;
use App\Models\Isp;
use App\Models\Plan;
use App\Models\TipoPlan;
use App\Models\TipoServicio;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Importa los clientes del ISP Principal desde su base de datos anterior
 * (CSV ya procesado). REEMPLAZA los clientes actuales del Principal.
 */
class ImportarPrincipal extends Command
{
    protected $signature = 'principal:importar {archivo=storage/app/import/principal_clientes.csv}';

    protected $description = 'Importa/reemplaza los clientes del ISP Principal desde el CSV procesado.';

    private const COLOR_ESTADO = [
        'Activo' => '#22c55e',
        'Retirado' => '#ef4444',
        'Corte' => '#f59e0b',
        'Suspendido' => '#f59e0b',
        'Pendiente' => '#3b82f6',
        'Preferido' => '#8b5cf6',
        'Promocional' => '#3b82f6',
    ];

    private array $estadoCache = [];
    private array $barrioCache = [];
    private array $planCache = [];
    private array $codigosVistos = [];

    public function handle(): int
    {
        $ruta = base_path($this->argument('archivo'));
        if (! file_exists($ruta)) {
            $this->error("No se encontró el archivo: {$ruta}");

            return self::FAILURE;
        }

        $isp = Isp::where('tipo', 'principal')->first();
        if (! $isp) {
            $this->error('No existe el ISP Principal.');

            return self::FAILURE;
        }

        $tipoPlanBase = TipoPlan::pluck('id', 'nombre');       // nombre => id
        $tipoServicioBase = TipoServicio::pluck('id', 'nombre');
        $ciudad = Ciudad::firstOrCreate(['nombre' => 'Bogotá'], ['codigo_dane' => '11001']);

        $handle = fopen($ruta, 'r');
        fgetcsv($handle); // encabezado

        $importados = 0;
        $omitidos = 0;

        DB::transaction(function () use ($handle, $isp, $ciudad, $tipoPlanBase, $tipoServicioBase, &$importados, &$omitidos) {
            // Reemplazo: borramos definitivamente los clientes actuales del Principal.
            Cliente::where('isp_id', $isp->id)->forceDelete();

            while (($f = fgetcsv($handle)) !== false) {
                [$codigo, $tipoId, $ident, $tipoCon, $n1, $n2, $a1, $a2, $t1, $t2, $correo,
                    $barrioNom, $prefijo, $direccion, $planTipo, $planServ, $planMbps, $planValor,
                    $estadoNom, $fechaInst, $diaCorte] = array_pad($f, 21, '');

                // Código único por ISP: si se repite, lo saltamos.
                if ($codigo === '' || isset($this->codigosVistos[$codigo])) {
                    $omitidos++;
                    continue;
                }
                $this->codigosVistos[$codigo] = true;

                $estado = $this->obtenerEstado($isp->id, $estadoNom ?: 'Activo');
                $barrio = $this->obtenerBarrio($isp->id, $ciudad->id, $barrioNom ?: 'Sin asignar', $prefijo ?: 'GEN');
                $plan = $this->obtenerPlan($isp->id, $tipoPlanBase, $tipoServicioBase, $planTipo, $planServ, $planMbps, $planValor);

                // Separa persona (titular) y servicio; reutiliza el titular si ya existe.
                Cliente::crearConTitular([
                    'isp_id' => $isp->id,
                    'codigo_cliente' => $codigo,
                    'tipo_identificacion' => in_array($tipoId, ['CC', 'CE', 'NIT', 'PA', 'TI', 'PPT', 'PEP']) ? $tipoId : 'CC',
                    // Sin identificación: valor único, para no fundir personas distintas en un mismo titular.
                    'identificacion' => $ident ?: 'SIN-ID-'.Str::upper(Str::random(8)),
                    // Se deduce del tipo de identificación al guardar el titular.
                    'tipo_contribuyente' => null,
                    'primer_nombre' => $n1 ?: 'N/A',
                    'segundo_nombre' => $n2 ?: null,
                    'primer_apellido' => $a1 ?: 'N/A',
                    'segundo_apellido' => $a2 ?: null,
                    'telefono_1' => $t1 ?: 'N/A',
                    'telefono_2' => $t2 ?: null,
                    'correo' => $correo ?: null,
                    'ciudad_id' => $ciudad->id,
                    'barrio_id' => $barrio->id,
                    'direccion' => $direccion ?: 'N/A',
                    'plan_id' => $plan->id,
                    'estado_id' => $estado->id,
                    'fecha_instalacion' => $this->fecha($fechaInst),
                    'dia_corte' => is_numeric($diaCorte) ? (int) $diaCorte : null,
                    'facturable' => false,
                ]);

                $importados++;
            }
        });

        fclose($handle);
        $this->info("Listo. Importados: {$importados}. Omitidos: {$omitidos}.");

        return self::SUCCESS;
    }

    private function obtenerEstado(int $ispId, string $nombre): EstadoCliente
    {
        $clave = $ispId.'|'.$nombre;

        return $this->estadoCache[$clave] ??= EstadoCliente::firstOrCreate(
            ['isp_id' => $ispId, 'nombre' => $nombre],
            ['color' => self::COLOR_ESTADO[$nombre] ?? '#6b7280'],
        );
    }

    private function obtenerBarrio(int $ispId, int $ciudadId, string $nombre, string $prefijo): Barrio
    {
        $clave = $ispId.'|'.$nombre;

        return $this->barrioCache[$clave] ??= Barrio::firstOrCreate(
            ['isp_id' => $ispId, 'ciudad_id' => $ciudadId, 'nombre' => $nombre],
            ['prefijo' => $prefijo],
        );
    }

    private function obtenerPlan($ispId, $tipoPlanBase, $tipoServicioBase, string $tipo, string $serv, string $mbps, string $valor): Plan
    {
        $cantidad = ($serv === 'TV' || $mbps === '') ? null : (int) $mbps;
        $tipoPlanId = $tipoPlanBase[$tipo] ?? $tipoPlanBase->first();
        $tipoServId = $tipoServicioBase[$serv] ?? $tipoServicioBase->first();
        $clave = implode('|', [$ispId, $tipoPlanId, $tipoServId, $cantidad]);

        return $this->planCache[$clave] ??= Plan::firstOrCreate(
            ['isp_id' => $ispId, 'tipo_plan_id' => $tipoPlanId, 'tipo_servicio_id' => $tipoServId, 'cantidad' => $cantidad],
            ['valor' => is_numeric($valor) ? (int) $valor : 0, 'activo' => true],
        );
    }

    private function fecha(?string $f): ?string
    {
        if (! $f || $f === '0000-00-00' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) {
            return null;
        }

        return $f;
    }
}
