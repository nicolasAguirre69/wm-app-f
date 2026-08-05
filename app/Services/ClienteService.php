<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
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
        return Cliente::query()
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
                fn (Builder $q) => $q->where('isp_id', $filtros['isp_id'])
            )
            // Filtro por estado de facturación ('1' = sí, '0' = no).
            ->when(
                isset($filtros['facturable']) && $filtros['facturable'] !== '',
                fn (Builder $q) => $q->where('facturable', $filtros['facturable'] === '1')
            )
            ->orderBy($filtros['sort'] ?? 'created_at', $filtros['direction'] ?? 'desc')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * Crea un cliente. Guarda el documento y asigna el usuario creador.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, ?UploadedFile $documento): Cliente
    {
        // Nunca dejamos el archivo crudo en los datos del modelo.
        unset($datos['documento_digitalizado']);

        if ($documento) {
            $datos['documento_digitalizado'] = $documento->store(self::CARPETA_DOCS, 'public');
        }

        // El usuario creador es el autenticado (null si es consola/super admin sin sesión web).
        $datos['usuario_creador_id'] = Auth::id();

        return Cliente::create($datos);
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
