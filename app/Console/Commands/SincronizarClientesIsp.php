<?php

namespace App\Console\Commands;

use App\Enums\TipoIdentificacion;
use App\Models\Barrio;
use App\Models\Cliente;
use App\Models\EstadoCliente;
use App\Models\Isp;
use App\Models\Scopes\IspScope;
use App\Models\Titular;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sincroniza los clientes de una ISP con su lista actualizada (archivo JSON
 * generado a partir del Excel que envía la ISP). El archivo MANDA:
 *
 *   - Persona del archivo que ya existe  -> se actualizan sus datos (nombres,
 *     teléfonos, correo) y los de su(s) servicio(s) (dirección, barrio, estado).
 *   - Persona del archivo que no existe   -> se crea titular + servicio, con el
 *     siguiente código (TV-0259, TV-0260...) y el plan de TV de la ISP.
 *   - Cada fila del archivo es un servicio: si una persona aparece 2 veces,
 *     debe tener 2 servicios (se crea el que falte); si tiene más servicios que
 *     filas, los sobrantes se retiran.
 *   - Servicio activo de una persona que NO está en el archivo -> Retirado.
 *   - Estado Activo  -> facturable; Retirado -> no facturable.
 *
 * Por defecto solo MUESTRA lo que haría. Con --aplicar escribe los cambios
 * (todo en una transacción: o se aplica completo o nada).
 *
 *   php artisan clientes:sincronizar storage/app/private/importaciones/netbel_2026_09.json
 *   php artisan clientes:sincronizar storage/app/private/importaciones/netbel_2026_09.json --aplicar
 */
class SincronizarClientesIsp extends Command
{
    protected $signature = 'clientes:sincronizar {archivo : JSON con {isp, filas[]}} {--aplicar : Escribir los cambios (sin esto solo muestra el plan)}';

    protected $description = 'Sincroniza los clientes de una ISP con su lista actualizada (el archivo manda).';

    /** Errores de digitación conocidos en los barrios del archivo. */
    private const ALIAS_BARRIOS = [
        'JUAN PABO II' => 'JUAN PABLO II',
    ];

    private const FIN_SIMULACION = '__fin_simulacion__';

    private Isp $isp;

    private int $estadoActivo;

    private int $estadoRetirado;

    private int $planTv;

    /** @var array<string, Barrio> nombre normalizado => barrio */
    private array $barrios = [];

    private int $ultimoCodigo = 0;

    /** @var list<array{accion: string, codigo: string, identificacion: string, detalle: string}> */
    private array $plan = [];

    /** @var list<string> */
    private array $avisos = [];

    public function handle(): int
    {
        $ruta = $this->argument('archivo');
        $ruta = is_file($ruta) ? $ruta : base_path($ruta);

        if (! is_file($ruta)) {
            $this->error("No se encontró el archivo: {$ruta}");

            return self::FAILURE;
        }

        $datos = json_decode((string) file_get_contents($ruta), true);

        if (! is_array($datos) || empty($datos['isp']) || ! is_array($datos['filas'] ?? null)) {
            $this->error('El archivo debe ser un JSON con las claves "isp" y "filas".');

            return self::FAILURE;
        }

        if (! $this->cargarCatalogos($datos['isp'])) {
            return self::FAILURE;
        }

        $aplicar = (bool) $this->option('aplicar');

        $this->info(($aplicar ? 'APLICANDO' : 'SIMULACIÓN (no se escribe nada)')." — ISP: {$this->isp->nombre} — filas: ".count($datos['filas']));

        try {
            DB::transaction(function () use ($datos, $aplicar) {
                $this->sincronizar(collect($datos['filas']), $aplicar);

                if (! $aplicar) {
                    // Simulación: se deshace todo lo que se haya preparado.
                    throw new \LogicException(self::FIN_SIMULACION);
                }
            });
        } catch (\LogicException $e) {
            if ($e->getMessage() !== self::FIN_SIMULACION) {
                throw $e;
            }
        }

        $this->mostrarResumen($aplicar);

        return self::SUCCESS;
    }

    private function cargarCatalogos(string $nombreIsp): bool
    {
        $isp = Isp::where('nombre', $nombreIsp)->first();

        if (! $isp) {
            $this->error("No existe la ISP \"{$nombreIsp}\".");

            return false;
        }

        $this->isp = $isp;

        $estados = EstadoCliente::withoutGlobalScope(IspScope::class)->where('isp_id', $isp->id)->pluck('id', 'nombre');

        if (! isset($estados['Activo'], $estados['Retirado'])) {
            $this->error('La ISP no tiene los estados Activo y Retirado.');

            return false;
        }

        $this->estadoActivo = (int) $estados['Activo'];
        $this->estadoRetirado = (int) $estados['Retirado'];
        $this->planTv = $isp->planTv()->id;

        foreach (Barrio::withoutGlobalScope(IspScope::class)->where('isp_id', $isp->id)->get() as $barrio) {
            $this->barrios[$this->normalizarTexto($barrio->nombre)] = $barrio;
        }

        // Último código TV-NNNN de la ISP (incluye eliminados: el código no se repite).
        $this->ultimoCodigo = (int) Cliente::withoutGlobalScopes()
            ->where('isp_id', $isp->id)
            ->where('codigo_cliente', 'like', 'TV-%')
            ->pluck('codigo_cliente')
            ->map(fn ($c) => (int) preg_replace('/\D/', '', $c))
            ->max();

        return true;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $filas
     */
    private function sincronizar(Collection $filas, bool $aplicar): void
    {
        // Titulares de la ISP (incluidos los archivados) por identificación normalizada.
        $titulares = Titular::withoutGlobalScopes()
            ->where('isp_id', $this->isp->id)
            ->get()
            ->keyBy(fn (Titular $t) => $this->normalizarId($t->identificacion));

        $vistos = [];

        foreach ($filas->groupBy(fn ($f) => $this->normalizarId($f['identificacion'] ?? '')) as $idNorm => $grupo) {
            if ($idNorm === '') {
                $this->avisos[] = 'Fila(s) sin identificación: '.$grupo->pluck('fila')->implode(', ').' (omitidas).';

                continue;
            }

            $vistos[$idNorm] = true;
            $titular = $titulares->get($idNorm);

            if ($titular) {
                $this->actualizarTitular($titular, $grupo->first());
            } else {
                $titular = $this->crearTitular($grupo->first());
            }

            $this->sincronizarServicios($titular, $grupo->values());
        }

        // Personas con servicio activo que ya no están en el archivo -> Retirado.
        foreach ($titulares as $idNorm => $titular) {
            if (isset($vistos[$idNorm])) {
                continue;
            }

            foreach ($this->serviciosDe($titular) as $servicio) {
                if ((int) $servicio->estado_id !== $this->estadoRetirado) {
                    $this->registrar('retirar (no está en el archivo)', $servicio->codigo_cliente, $titular->identificacion, $servicio->direccion);
                    $servicio->update(['estado_id' => $this->estadoRetirado, 'facturable' => false]);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function actualizarTitular(Titular $titular, array $fila): void
    {
        if ($titular->trashed()) {
            $titular->restore();
        }

        $titular->fill($this->datosPersona($fila, $titular));

        if ($titular->isDirty()) {
            $titular->save();
            // El hook de Titular normaliza (mayúsculas, teléfonos): solo se
            // reporta lo que de verdad quedó distinto.
            $cambios = array_diff(array_keys($titular->getChanges()), ['updated_at']);
            if ($cambios) {
                $this->registrar('actualizar persona', '', $titular->identificacion, implode(', ', $cambios));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function crearTitular(array $fila): Titular
    {
        $titular = new Titular($this->datosPersona($fila, null));
        $titular->isp_id = $this->isp->id;
        $titular->identificacion = trim((string) $fila['identificacion']);
        $titular->save();

        $this->registrar('crear persona', '', $titular->identificacion, trim(($fila['nombres'] ?? '').' '.($fila['apellidos'] ?? '')));

        return $titular;
    }

    /**
     * Empareja las filas del archivo de una persona con sus servicios:
     * primero por dirección igual, luego los que queden en orden. Filas sin
     * servicio -> se crea; servicios sin fila -> se retiran.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     */
    private function sincronizarServicios(Titular $titular, Collection $filas): void
    {
        // Activos primero, para que una fila "Activo" reutilice el servicio activo.
        $servicios = $this->serviciosDe($titular)
            ->sortBy(fn (Cliente $c) => [(int) $c->estado_id === $this->estadoActivo ? 0 : 1, $c->codigo_cliente])
            ->values();

        $libres = $servicios->keyBy('id');
        $pendientes = [];

        // 1) Misma dirección.
        foreach ($filas as $fila) {
            $dir = $this->normalizarDireccion($fila['direccion'] ?? '');
            $servicio = $libres->first(fn (Cliente $c) => $this->normalizarDireccion($c->direccion) === $dir);

            if ($servicio) {
                $libres->forget($servicio->id);
                $this->actualizarServicio($servicio, $fila);
            } else {
                $pendientes[] = $fila;
            }
        }

        // 2) Las filas restantes reutilizan los servicios restantes (cambio de dirección)...
        foreach ($pendientes as $fila) {
            $servicio = $libres->shift();

            if ($servicio) {
                $this->actualizarServicio($servicio, $fila);
            } else {
                // ...o, si no queda ninguno, se crea un servicio nuevo.
                $this->crearServicio($titular, $fila);
            }
        }

        // 3) Servicios de más (la persona tiene más servicios que filas) -> Retirado.
        foreach ($libres as $servicio) {
            if ((int) $servicio->estado_id !== $this->estadoRetirado) {
                $this->registrar('retirar (servicio sobrante)', $servicio->codigo_cliente, $titular->identificacion, $servicio->direccion);
                $servicio->update(['estado_id' => $this->estadoRetirado, 'facturable' => false]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function actualizarServicio(Cliente $servicio, array $fila): void
    {
        $servicio->fill($this->datosServicio($fila, $servicio));

        if ($servicio->isDirty()) {
            $cambios = $servicio->getDirty();
            $servicio->save();

            $accion = isset($cambios['estado_id'])
                ? ((int) $cambios['estado_id'] === $this->estadoRetirado ? 'retirar (rojo en el archivo)' : 'reactivar')
                : 'actualizar servicio';

            $this->registrar($accion, $servicio->codigo_cliente, (string) $fila['identificacion'], implode(', ', array_keys($cambios)));
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function crearServicio(Titular $titular, array $fila): void
    {
        $codigo = sprintf('TV-%04d', ++$this->ultimoCodigo);

        $servicio = new Cliente([
            'isp_id' => $this->isp->id,
            'titular_id' => $titular->id,
            'codigo_cliente' => $codigo,
            'plan_id' => $this->planTv,
            ...$this->datosServicio($fila, null),
        ]);
        $servicio->save();

        $this->registrar('crear servicio', $codigo, $titular->identificacion, ($fila['direccion'] ?? '').' — '.($fila['estado'] ?? 'Activo'));
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function datosPersona(array $fila, ?Titular $actual): array
    {
        $identificacion = trim((string) $fila['identificacion']);

        // NIT: lo dice el tipo, o la identificación lleva dígito de verificación (900668255-4).
        $tipo = TipoIdentificacion::tryFrom(strtoupper((string) ($fila['tipo_identificacion'] ?? 'CC'))) ?? TipoIdentificacion::CC;
        if (str_contains($identificacion, '-') || $actual?->tipo_identificacion === TipoIdentificacion::NIT) {
            $tipo = TipoIdentificacion::NIT;
        }

        $datos = ['tipo_identificacion' => $tipo];

        if ($tipo === TipoIdentificacion::NIT) {
            // Empresa: la razón social va completa en primer_nombre.
            $datos += [
                'primer_nombre' => trim(($fila['nombres'] ?? '').' '.($fila['apellidos'] ?? '')),
                'segundo_nombre' => null,
                'primer_apellido' => null,
                'segundo_apellido' => null,
            ];
        } else {
            [$n1, $n2] = $this->partir($fila['nombres'] ?? null);
            [$a1, $a2] = $this->partir($fila['apellidos'] ?? null);
            $datos += [
                'primer_nombre' => $n1 ?? $actual?->primer_nombre ?? 'N/A',
                'segundo_nombre' => $n2,
                'primer_apellido' => $a1 ?? $actual?->primer_apellido,
                'segundo_apellido' => $a2,
            ];
        }

        // Teléfonos: grupos de 7 a 11 dígitos. Si el archivo no trae uno válido,
        // se conserva el que había.
        $telefonos = $this->telefonos($fila['telefonos'] ?? null);
        if ($telefonos === [] && ! empty($fila['telefonos'])) {
            $this->avisos[] = "Fila {$fila['fila']} ({$identificacion}): teléfono \"{$fila['telefonos']}\" no válido; se conserva el anterior.";
        }
        $datos['telefono_1'] = $telefonos[0] ?? $actual?->telefono_1;
        $datos['telefono_2'] = $telefonos[1] ?? ($telefonos ? null : $actual?->telefono_2);

        // Correo: solo si el archivo trae uno (no se borra el existente).
        if (! empty($fila['correo'])) {
            $datos['correo'] = mb_strtolower(trim((string) $fila['correo']));
        }

        return $datos;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function datosServicio(array $fila, ?Cliente $actual): array
    {
        $activo = ($fila['estado'] ?? 'Activo') !== 'Retirado';

        $datos = [
            'direccion' => trim((string) ($fila['direccion'] ?? '')) ?: ($actual?->direccion ?? 'N/A'),
            'estado_id' => $activo ? $this->estadoActivo : $this->estadoRetirado,
            'facturable' => $activo,
            'puerto_alquilado' => false,
        ];

        if ($activo) {
            $datos['motivo_no_facturable'] = null;
        }

        $barrio = $this->barrio($fila['barrio'] ?? null);
        if ($barrio) {
            $datos['barrio_id'] = $barrio->id;
        } elseif (! $actual) {
            // Servicio nuevo sin barrio reconocible: no se puede crear sin barrio.
            throw new \RuntimeException("Fila {$fila['fila']}: barrio \"{$fila['barrio']}\" no existe en {$this->isp->nombre}. Créelo o corríjalo en el archivo.");
        } else {
            $this->avisos[] = "Fila {$fila['fila']}: barrio \"{$fila['barrio']}\" no existe; se conserva el anterior.";
        }

        return $datos;
    }

    private function barrio(?string $nombre): ?Barrio
    {
        $clave = $this->normalizarTexto((string) $nombre);
        $clave = self::ALIAS_BARRIOS[$clave] ?? $clave;

        return $this->barrios[$clave] ?? null;
    }

    /**
     * @return Collection<int, Cliente>
     */
    private function serviciosDe(Titular $titular): Collection
    {
        return Cliente::withoutGlobalScope(IspScope::class)
            ->without('titular')
            ->where('titular_id', $titular->id)
            ->orderBy('codigo_cliente')
            ->get();
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function partir(?string $texto): array
    {
        $partes = preg_split('/\s+/', trim((string) $texto), 2, PREG_SPLIT_NO_EMPTY) ?: [];

        return [$partes[0] ?? null, $partes[1] ?? null];
    }

    /**
     * @return list<string>
     */
    private function telefonos(mixed $valor): array
    {
        preg_match_all('/\d+/', (string) $valor, $m);

        return array_values(array_filter($m[0], fn ($d) => strlen($d) >= 7 && strlen($d) <= 11));
    }

    private function normalizarId(?string $id): string
    {
        return preg_replace('/\D/', '', (string) $id) ?? '';
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = Str::upper(Str::ascii(trim($texto)));

        return preg_replace('/\s+/', ' ', $texto) ?? '';
    }

    private function normalizarDireccion(?string $dir): string
    {
        $d = ' '.$this->normalizarTexto((string) $dir).' ';
        $d = preg_replace(
            ['/\bCALLE\b|\bCLL\b/', '/\bCARRERA\b|\bKR\b|\bCRA\b|\bCR\b/', '/\bDIAGONAL\b|\bDIAG\b/', '/\bTRANSVERSAL\b|\bTRANSV\b/'],
            ['CL', 'KR', 'DG', 'TV'],
            $d,
        );

        return preg_replace('/[^A-Z0-9]/', '', (string) $d) ?? '';
    }

    private function registrar(string $accion, string $codigo, string $identificacion, string $detalle): void
    {
        $this->plan[] = compact('accion', 'codigo', 'identificacion', 'detalle');
    }

    private function mostrarResumen(bool $aplicar): void
    {
        $plan = collect($this->plan);

        $this->newLine();
        $this->table(['Acción', 'Cantidad'], $plan->countBy('accion')->sortKeys()->map(fn ($n, $a) => [$a, $n])->values());

        $detalle = $plan->filter(fn ($p) => ! in_array($p['accion'], ['actualizar persona', 'actualizar servicio'], true));
        if ($detalle->isNotEmpty()) {
            $this->newLine();
            $this->line('Detalle (crear / retirar / reactivar):');
            $this->table(['Acción', 'Código', 'Identificación', 'Detalle'], $detalle->map(fn ($p) => array_values($p))->values());
        }

        foreach (array_unique($this->avisos) as $aviso) {
            $this->warn($aviso);
        }

        // Reporte completo en CSV junto al archivo de importaciones.
        if (app()->runningUnitTests()) {
            return;
        }

        $reporte = storage_path('app/private/importaciones/reporte_'.Str::slug($this->isp->nombre).'_'.now()->format('Ymd_His').($aplicar ? '' : '_simulacion').'.csv');
        @mkdir(dirname($reporte), 0775, true);
        $f = fopen($reporte, 'w');
        fwrite($f, "\xEF\xBB\xBF"); // BOM: Excel abre bien las tildes
        fputcsv($f, ['accion', 'codigo', 'identificacion', 'detalle'], ';');
        foreach ($this->plan as $p) {
            fputcsv($f, array_values($p), ';');
        }
        fclose($f);

        $this->newLine();
        $this->info('Reporte completo: '.$reporte);
        $this->info($aplicar
            ? 'Cambios APLICADOS.'
            : 'Fue una simulación: no se escribió nada. Si está de acuerdo, repita el comando con --aplicar.');
    }
}

