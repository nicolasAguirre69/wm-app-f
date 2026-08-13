<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\EstadoCliente;
use App\Models\Isp;
use App\Models\Scopes\IspScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Lógica de negocio de Clientes. Aislado por ISP vía BelongsToIsp.
 */
class ClienteService
{
    // Carpeta dentro del disco 'public' donde se guardan los documentos.
    private const CARPETA_DOCS = 'clientes/documentos';

    /**
     * @param  array{search?: string, sort?: string, direction?: string, isp_id?: string, facturable?: string}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        $paginador = Cliente::query()
            // Cargamos las relaciones que mostramos en la tabla (evita N+1).
            // 'isp' se usa en la vista del Super Admin.
            ->with(['isp', 'ciudad', 'barrio', 'plan.tipoServicio', 'estado'])
            ->when(
                ! empty($filtros['search']),
                fn (Builder $q) => $q->where(function (Builder $g) use ($filtros) {
                    $s = '%'.$filtros['search'].'%';
                    $g->where('codigo_cliente', 'like', $s)
                        ->orWhere('identificacion', 'like', $s)
                        ->orWhere('primer_nombre', 'like', $s)
                        ->orWhere('primer_apellido', 'like', $s)
                        ->orWhere('correo', 'like', $s);
                })
            )
            // Filtro por ISP (solo tiene efecto para el Super Admin, cuyo
            // Global Scope está desactivado; un usuario normal ya está acotado).
            ->when(
                ! empty($filtros['isp_id']),
                fn (Builder $q) => $q->where('isp_id', Isp::decodeHashid($filtros['isp_id']))
            )
            // Filtro por estado de facturación ('1' = sí, '0' = no).
            ->when(
                isset($filtros['facturable']) && $filtros['facturable'] !== '',
                fn (Builder $q) => $q->where('facturable', $filtros['facturable'] === '1')
            )
            // Filtro por estado del cliente (por nombre, universal entre ISPs).
            ->when(
                ! empty($filtros['estado']),
                fn (Builder $q) => $q->whereHas('estado', fn (Builder $e) => $e->where('nombre', $filtros['estado']))
            )
            ->orderBy($filtros['sort'] ?? 'created_at', $filtros['direction'] ?? 'desc')
            ->paginate(10)
            ->withQueryString();

        // BLINDAJE: si quien consulta NO es Super Admin, ocultamos el estado de
        // facturación por completo — ni siquiera viaja en el JSON al navegador.
        if (! Auth::user()?->is_super_admin) {
            $paginador->getCollection()->makeHidden(['facturable', 'motivo_no_facturable']);
        }

        return $paginador;
    }

    /**
     * Crea un cliente. Guarda el documento y asigna el usuario creador.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, ?UploadedFile $documento, bool $trasladar = false): Cliente
    {
        // Todo el traslado va en una transacción: o se crea el nuevo Y se retira
        // el anterior, o no pasa nada. Evita facturar dos veces por un fallo a medias.
        return DB::transaction(function () use ($datos, $documento, $trasladar) {
            // Campos que no van al modelo.
            unset($datos['documento_digitalizado'], $datos['trasladar']);

            if ($documento) {
                $datos['documento_digitalizado'] = $documento->store(self::CARPETA_DOCS, 'public');
            }

            // El usuario creador es el autenticado.
            $datos['usuario_creador_id'] = Auth::id();

            $cliente = Cliente::create($datos);

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
            ->where('identificacion', $nuevo->identificacion)
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
     * Actualiza un cliente. Si llega un documento nuevo, reemplaza el anterior.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Cliente $cliente, array $datos, ?UploadedFile $documento): Cliente
    {
        unset($datos['documento_digitalizado']);

        if ($documento) {
            // Borramos el documento anterior para no dejar archivos huérfanos.
            if ($cliente->documento_digitalizado) {
                Storage::disk('public')->delete($cliente->documento_digitalizado);
            }
            $datos['documento_digitalizado'] = $documento->store(self::CARPETA_DOCS, 'public');
        }

        $cliente->update($datos);

        return $cliente;
    }

    public function eliminar(Cliente $cliente): void
    {
        // Soft delete: NO borramos el archivo (podríamos restaurar el cliente).
        $cliente->delete();
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
