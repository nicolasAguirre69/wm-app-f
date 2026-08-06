<?php

namespace App\Console\Commands;

use App\Enums\TipoContribuyente;
use App\Enums\TipoIdentificacion;
use App\Models\Barrio;
use App\Models\Ciudad;
use App\Models\Cliente;
use App\Models\EstadoCliente;
use App\Models\Isp;
use App\Models\Plan;
use App\Models\TipoPlan;
use App\Models\TipoServicio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarClientes extends Command
{
    protected $signature = 'clientes:importar {archivo=storage/app/import/clientes.csv}';

    protected $description = 'Importa clientes desde un CSV (columnas: tipo_id, nombres, apellidos, identificacion, barrio, direccion, telefonos, correo, isp)';

    /**
     * Texto del archivo → nombre corto del ISP. El primero es el Principal.
     */
    private const MAPA_ISP = [
        'Web Master Colombia' => 'principal',
        'Telecomunicaciones Avanzadas del Sur' => 'cliente',
        'Nube Net' => 'cliente',
        'Net Bell' => 'cliente',
        'Inttel Go' => 'cliente',
    ];

    // Caches para no consultar la BD repetidamente.
    private array $ispCache = [];
    private array $ciudadCache = [];
    private array $planCache = [];
    private array $estadoCache = [];
    private array $barrioCache = [];
    private array $contador = []; // codigo secuencial por ISP

    public function handle(): int
    {
        $ruta = base_path($this->argument('archivo'));

        if (! file_exists($ruta)) {
            $this->error("No se encontró el archivo: {$ruta}");

            return self::FAILURE;
        }

        $tipoPlan = TipoPlan::where('nombre', 'Hogar')->first();
        $tipoServicio = TipoServicio::where('nombre', 'TV')->first();

        if (! $tipoPlan || ! $tipoServicio) {
            $this->error('Faltan los catálogos globales Hogar / TV. Corre primero: php artisan db:seed');

            return self::FAILURE;
        }

        $handle = fopen($ruta, 'r');
        fgetcsv($handle); // saltar encabezado

        $importados = 0;
        $omitidos = 0;

        DB::transaction(function () use ($handle, $tipoPlan, $tipoServicio, &$importados, &$omitidos) {
            // Limpieza previa: borramos definitivamente los clientes ya
            // importados (código TV-) para recargar todo desde el archivo.
            // Los clientes creados a mano (otros códigos) NO se tocan.
            Cliente::where('codigo_cliente', 'like', 'TV-%')->forceDelete();

            while (($fila = fgetcsv($handle)) !== false) {
                [$tipoId, $nombres, $apellidos, $identificacion, $barrioNom, $direccion, $telefonos, $correo, $ispTexto] = array_pad($fila, 9, '');

                $ispNombre = $this->resolverIspNombre($ispTexto);
                if (! $ispNombre) {
                    $omitidos++;
                    continue;
                }

                $isp = $this->obtenerIsp($ispNombre);
                $ciudad = $this->obtenerCiudad();
                $estado = $this->obtenerEstado($isp->id);
                $plan = $this->obtenerPlan($isp->id, $tipoPlan->id, $tipoServicio->id);
                $barrio = $this->obtenerBarrio($isp->id, $ciudad->id, trim($barrioNom) ?: 'Sin asignar');

                // Cada fila es un servicio: importamos todas (un cliente puede
                // tener 1 o 2 servicios, en la misma dirección o no).
                [$primerNombre, $segundoNombre] = $this->partir($nombres);
                [$primerApellido, $segundoApellido] = $this->partir($apellidos);

                Cliente::create([
                    'isp_id' => $isp->id,
                    'codigo_cliente' => $this->siguienteCodigo($isp->id),
                    'tipo_identificacion' => $this->tipoIdValido($tipoId),
                    'identificacion' => $identificacion ?: 'SIN-ID',
                    'tipo_contribuyente' => TipoContribuyente::Natural->value,
                    'primer_nombre' => $primerNombre ?: 'N/A',
                    'segundo_nombre' => $segundoNombre,
                    'primer_apellido' => $primerApellido ?: 'N/A',
                    'segundo_apellido' => $segundoApellido,
                    'telefono_1' => $telefonos ?: 'N/A',
                    'telefono_2' => null,
                    'correo' => $correo ?: null,
                    'ciudad_id' => $ciudad->id,
                    'barrio_id' => $barrio->id,
                    'direccion' => $direccion ?: 'N/A',
                    'plan_id' => $plan->id,
                    'estado_id' => $estado->id,
                    'fecha_instalacion' => null,
                    'dia_corte' => null,
                    'usuario_creador_id' => null,
                    'documento_digitalizado' => null,
                    'facturable' => false,
                ]);

                $importados++;
            }
        });

        fclose($handle);

        $this->info("Importación finalizada. Importados: {$importados}. Omitidos: {$omitidos}.");

        return self::SUCCESS;
    }

    private function resolverIspNombre(string $texto): ?string
    {
        foreach (array_keys(self::MAPA_ISP) as $nombre) {
            if (str_contains($texto, $nombre)) {
                return $nombre;
            }
        }

        return null;
    }

    private function obtenerIsp(string $nombre): Isp
    {
        if (isset($this->ispCache[$nombre])) {
            return $this->ispCache[$nombre];
        }

        if (self::MAPA_ISP[$nombre] === 'principal') {
            // El Principal ya existe; le ponemos el nombre real de la empresa.
            $isp = Isp::where('tipo', 'principal')->first();
            $isp->update(['nombre' => $nombre]);
        } else {
            $isp = Isp::firstOrCreate(
                ['nombre' => $nombre, 'tipo' => 'cliente'],
                ['activo' => true],
            );
        }

        return $this->ispCache[$nombre] = $isp;
    }

    private function obtenerCiudad(): Ciudad
    {
        // Ciudad es catálogo global.
        return $this->ciudadCache['Bogotá'] ??= Ciudad::firstOrCreate(['nombre' => 'Bogotá']);
    }

    private function obtenerEstado(int $ispId): EstadoCliente
    {
        return $this->estadoCache[$ispId] ??= EstadoCliente::firstOrCreate(
            ['isp_id' => $ispId, 'nombre' => 'Activo'],
            ['color' => '#22c55e'],
        );
    }

    private function obtenerPlan(int $ispId, int $tipoPlanId, int $tipoServicioId): Plan
    {
        return $this->planCache[$ispId] ??= Plan::firstOrCreate(
            ['isp_id' => $ispId, 'tipo_plan_id' => $tipoPlanId, 'tipo_servicio_id' => $tipoServicioId, 'cantidad' => null],
            ['valor' => 0, 'activo' => true],
        );
    }

    private function obtenerBarrio(int $ispId, int $ciudadId, string $nombre): Barrio
    {
        $clave = $ispId.'|'.$nombre;

        return $this->barrioCache[$clave] ??= Barrio::firstOrCreate(
            ['isp_id' => $ispId, 'ciudad_id' => $ciudadId, 'nombre' => $nombre],
            ['prefijo' => $this->prefijo($nombre)],
        );
    }

    private function prefijo(string $nombre): string
    {
        $letras = preg_replace('/[^A-Za-z]/', '', $nombre);

        return strtoupper(substr($letras ?: 'BAR', 0, 3));
    }

    private function siguienteCodigo(int $ispId): string
    {
        if (! isset($this->contador[$ispId])) {
            // Contamos solo los importados (TV-) para reiniciar la numeración
            // tras la limpieza previa.
            $this->contador[$ispId] = Cliente::where('isp_id', $ispId)
                ->where('codigo_cliente', 'like', 'TV-%')
                ->count();
        }

        $this->contador[$ispId]++;

        return 'TV-'.str_pad((string) $this->contador[$ispId], 4, '0', STR_PAD_LEFT);
    }

    private function tipoIdValido(string $valor): string
    {
        return TipoIdentificacion::tryFrom($valor)?->value ?? TipoIdentificacion::CC->value;
    }

    /**
     * Parte un texto en dos: primera palabra y el resto.
     *
     * @return array{0: string, 1: ?string}
     */
    private function partir(string $texto): array
    {
        $partes = preg_split('/\s+/', trim($texto), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($partes)) {
            return ['', null];
        }

        $primero = array_shift($partes);
        $resto = empty($partes) ? null : implode(' ', $partes);

        return [$primero, $resto];
    }
}
