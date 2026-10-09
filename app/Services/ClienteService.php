<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\DocumentoCliente;
use App\Models\EstadoCliente;
use App\Models\Isp;
use App\Models\Scopes\IspScope;
use App\Models\Titular;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de negocio de Clientes. Aislado por ISP vía BelongsToIsp.
 */
class ClienteService
{

    /**
     * Lista de TITULARES (personas), cada uno con sus servicios (clientes).
     *
     * Los filtros de estado y facturable se aplican a los servicios: se
     * muestran los titulares que tienen al menos un servicio que cumple, y en
     * el desplegable solo esos servicios.
     *
     * @param  array{search?: string, sort?: string, direction?: string, isp_id?: string, facturable?: string, estado?: string, puerto?: string}  $filtros
     * @return array{titulares: LengthAwarePaginator, totalServicios: int}
     */
    public function listar(array $filtros): array
    {
        // Filtro que deben cumplir los servicios (estado / facturable). Sin tipos
        // en los parámetros: se usa con Builder (whereHas/withCount) y con la
        // relación (carga de los servicios).
        $filtroServicio = function ($q) use ($filtros): void {
            if (isset($filtros['facturable']) && $filtros['facturable'] !== '') {
                $q->where('facturable', $filtros['facturable'] === '1');
            }
            if (! empty($filtros['estado'])) {
                $q->whereHas('estado', fn ($e) => $e->where('nombre', $filtros['estado']));
            }
            if (isset($filtros['puerto']) && $filtros['puerto'] !== '') {
                $q->where('puerto_alquilado', $filtros['puerto'] === '1');
            }
        };

        $query = Titular::query()
            // Solo personas con al menos un servicio (que cumpla los filtros).
            ->whereHas('clientes', $filtroServicio)
            ->withCount(['clientes as servicios_count' => $filtroServicio])
            ->with([
                'isp:id,nombre,tipo',
                // Servicios del titular, sin volver a cargar el titular en cada uno.
                'clientes' => function ($q) use ($filtroServicio) {
                    $q->without('titular')
                        ->with(['barrio', 'barrio.ciudad:id,nombre', 'plan.tipoServicio', 'plan.tipoPlan', 'estado'])
                        // Solo si tiene documento (sin traer el archivo).
                        ->withExists('documento as tiene_documento')
                        // Tickets de soporte abiertos del servicio.
                        ->withCount(['tickets as tickets_abiertos' => fn ($t) => $t->where('estado', 'abierto')])
                        ->orderBy('codigo_cliente');
                    $filtroServicio($q);
                },
            ])
            // Búsqueda: cada palabra debe aparecer en algún dato de la persona o
            // en el código/dirección de alguno de sus servicios. Así se puede
            // buscar por nombre completo ("Miguel Velazques") o por código.
            ->when(! empty($filtros['search']), function (Builder $q) use ($filtros) {
                foreach (preg_split('/\s+/', trim($filtros['search'])) as $palabra) {
                    $like = '%'.$palabra.'%';
                    $q->where(function (Builder $g) use ($like) {
                        $g->where('identificacion', 'like', $like)
                            ->orWhere('primer_nombre', 'like', $like)
                            ->orWhere('segundo_nombre', 'like', $like)
                            ->orWhere('primer_apellido', 'like', $like)
                            ->orWhere('segundo_apellido', 'like', $like)
                            ->orWhere('correo', 'like', $like)
                            ->orWhere('telefono_1', 'like', $like)
                            ->orWhereHas('clientes', fn (Builder $c) => $c->where(
                                fn (Builder $c) => $c->where('codigo_cliente', 'like', $like)
                                    ->orWhere('direccion', 'like', $like)
                            ));
                    });
                }
            })
            // Filtro por ISP (solo tiene efecto para el Super Admin, cuyo
            // Global Scope está desactivado; un usuario normal ya está acotado).
            ->when(
                ! empty($filtros['isp_id']),
                fn (Builder $q) => $q->where('isp_id', Isp::decodeHashid($filtros['isp_id']))
            );

        $this->aplicarOrden($query, $filtros['sort'] ?? null, $filtros['direction'] ?? null);

        // Total de SERVICIOS que cumplen el filtro (para la tarjeta del Super Admin).
        $totalServicios = Cliente::query()
            ->whereIn('titular_id', (clone $query)->reorder()->select('titulares.id'))
            ->tap($filtroServicio)
            ->count();

        $paginador = $query->paginate(10)->withQueryString();

        // BLINDAJE: si quien consulta NO es Super Admin, ocultamos el estado de
        // facturación por completo — ni siquiera viaja en el JSON al navegador.
        if (! Auth::user()?->is_super_admin) {
            $paginador->getCollection()->each(
                fn (Titular $t) => $t->clientes->makeHidden(['facturable', 'motivo_no_facturable'])
            );
        }

        return ['titulares' => $paginador, 'totalServicios' => $totalServicios];
    }

    /**
     * Ordena la lista de titulares por una columna PERMITIDA (whitelist: evita
     * inyección SQL porque el 'sort' viene del cliente).
     */
    private function aplicarOrden(Builder $query, ?string $sort, ?string $direction): void
    {
        $dir = $direction === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'nombre' => $query->orderBy('primer_nombre', $dir)->orderBy('primer_apellido', $dir),
            'apellido' => $query->orderBy('primer_apellido', $dir)->orderBy('primer_nombre', $dir),
            'identificacion' => $query->orderBy('identificacion', $dir),
            'servicios' => $query->orderBy('servicios_count', $dir),
            // Por defecto: más recientes primero.
            default => $query->orderBy('created_at', 'desc'),
        };
    }

    /**
     * Crea un servicio (cliente). Guarda el documento y asigna el usuario creador.
     *
     *  - Sin $titular: cliente nuevo; los datos de la persona vienen en $datos
     *    y se crea (o reutiliza) su titular.
     *  - Con $titular: "Agregar servicio" a una persona que ya existe; solo
     *    llegan datos del servicio.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, ?UploadedFile $documento, bool $trasladar = false, ?Titular $titular = null): Cliente
    {
        // Todo el traslado va en una transacción: o se crea el nuevo Y se retira
        // el anterior, o no pasa nada. Evita facturar dos veces por un fallo a medias.
        return DB::transaction(function () use ($datos, $documento, $trasladar, $titular) {
            // Campos que no van al modelo.
            unset($datos['documento_digitalizado'], $datos['trasladar'], $datos['titular']);

            // El usuario creador es el autenticado. El servicio va en la ISP del
            // titular (si se agrega a uno existente) o en la del usuario.
            $datos['usuario_creador_id'] = Auth::id();
            $datos['isp_id'] = $titular?->isp_id ?? Auth::user()->isp_id;

            // ISP cliente sin planes propios: el plan siempre es su plan de TV,
            // asignado automáticamente. Puerto alquilado: solo la principal.
            $isp = Isp::find($datos['isp_id']);
            if ($isp && ! $isp->tienePlanesPropios()) {
                $datos['plan_id'] = $isp->planTv()->id;
            }
            if ($isp && ! $isp->esPrincipal()) {
                $datos['puerto_alquilado'] = false;
            }

            $cliente = $titular
                ? Cliente::create([
                    ...Arr::except($datos, [...Titular::CAMPOS, 'ciudad_id']),
                    'titular_id' => $titular->id,
                ])
                : Cliente::crearConTitular($datos);

            // El documento se guarda DENTRO de la base (tabla documentos_cliente).
            if ($documento) {
                DocumentoCliente::guardarPara($cliente, $documento);
            }

            // Si es un traslado, retiramos al mismo cliente en las demás ISP.
            if ($trasladar) {
                $this->retirarEnOtrasIsps($cliente);
            }

            return $cliente;
        });
    }

    /**
     * Marca como "Retirado" (y no facturable) al mismo cliente —misma
     * identificación— en las demás ISP, para que no se le facture dos veces
     * tras un traslado. Cada ISP tiene su propio estado "Retirado".
     */
    private function retirarEnOtrasIsps(Cliente $nuevo): void
    {
        $otros = Cliente::withoutGlobalScope(IspScope::class)
            ->whereHas('titular', fn (Builder $t) => $t->where('identificacion', $nuevo->identificacion))
            ->where('id', '!=', $nuevo->id)
            ->where('isp_id', '!=', $nuevo->isp_id)
            ->get();

        foreach ($otros as $otro) {
            $retirado = EstadoCliente::withoutGlobalScope(IspScope::class)
                ->where('isp_id', $otro->isp_id)
                ->where('nombre', 'Retirado')
                ->first();

            // Si esa ISP no tiene el estado "Retirado", no tocamos el registro.
            if ($retirado) {
                $otro->update(['estado_id' => $retirado->id, 'facturable' => false]);
            }
        }
    }

    /**
     * Actualiza un SERVICIO (código, ubicación, plan, estado...). Los datos de
     * la persona se editan aparte (actualizarTitular). Si llega un documento
     * nuevo, reemplaza el anterior.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Cliente $cliente, array $datos, ?UploadedFile $documento): Cliente
    {
        $datos = Arr::except($datos, ['documento_digitalizado', 'ciudad_id', ...Titular::CAMPOS]);

        // Sin plan (ISP cliente: el formulario no lo pide) se conserva el actual.
        if (empty($datos['plan_id'])) {
            unset($datos['plan_id']);
        }

        // Puerto alquilado: solo existe en la ISP principal.
        if (! $cliente->isp?->esPrincipal()) {
            $datos['puerto_alquilado'] = false;
        }

        DB::transaction(function () use ($cliente, $datos, $documento) {
            $cliente->update($datos);

            // Documento nuevo: reemplaza al anterior (en la base).
            if ($documento) {
                DocumentoCliente::guardarPara($cliente, $documento);
            }
        });

        return $cliente;
    }

    /**
     * Actualiza los datos de la PERSONA. El cambio se ve en todos sus servicios.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizarTitular(Titular $titular, array $datos): Titular
    {
        $titular->update(Arr::only($datos, Titular::CAMPOS));

        return $titular;
    }

    /**
     * Elimina (borrado lógico) un servicio. Si la persona queda sin servicios,
     * también se archiva; si vuelve a contratar, se restaura con su cédula.
     */
    public function eliminar(Cliente $cliente): void
    {
        DB::transaction(function () use ($cliente) {
            // Soft delete: NO borramos el archivo (podríamos restaurar el cliente).
            $cliente->delete();

            $titular = $cliente->titular;

            if ($titular && ! $titular->clientes()->withoutGlobalScope(IspScope::class)->exists()) {
                $titular->delete();
            }
        });
    }

    /**
     * Marca o desmarca un cliente como facturable (solo Super Admin).
     */
    public function marcarFacturable(Cliente $cliente, bool $facturable, ?string $motivo): Cliente
    {
        $cliente->update([
            'facturable' => $facturable,
            'motivo_no_facturable' => $facturable ? null : $motivo,
        ]);

        return $cliente;
    }
}
