<?php

namespace App\Http\Controllers;

use App\Enums\CategoriaIsp;
use App\Enums\TipoIsp;
use App\Http\Requests\Isp\StoreIspRequest;
use App\Http\Requests\Isp\UpdateIspRequest;
use App\Models\Isp;
use App\Models\IspLogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class IspController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Isp::class);

        $filtros = $this->filtrosDe($request, ['search']);

        $isps = Isp::withCount(['clientes', 'users'])
            ->withExists('logo as tiene_logo')
            ->when(
                ! empty($filtros['search']),
                fn ($q) => $q->where('nombre', 'like', '%'.$filtros['search'].'%')
            )
            ->orderByDesc('tipo') // principal primero
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Isp $isp) => [
                'id' => $isp->id,
                'hashid' => $isp->hashid,
                'nombre' => $isp->nombre,
                'tipo' => $isp->tipo->value,
                'tipo_label' => $isp->tipo->label(),
                'activo' => $isp->activo,
                'id_producto' => $isp->id_producto,
                'categoria' => $isp->categoria?->value,
                'categoria_label' => $isp->categoria?->label(),
                'clientes_count' => $isp->clientes_count,
                'users_count' => $isp->users_count,
                'es_principal' => $isp->tipo === TipoIsp::Principal,
                'nit' => $isp->nit,
                'direccion' => $isp->direccion,
                'telefono' => $isp->telefono,
                'tiene_logo' => (bool) $isp->tiene_logo,
            ]);

        return Inertia::render('isps/index', [
            'isps' => $isps,
            'categorias' => CategoriaIsp::opciones(),
            'filtros' => $filtros,
        ]);
    }

    public function store(StoreIspRequest $request): RedirectResponse
    {
        $this->authorize('create', Isp::class);

        // Desde la interfaz siempre se crean ISPs de tipo Cliente.
        $isp = Isp::create([
            'nombre' => $request->validated('nombre'),
            'tipo' => TipoIsp::Cliente->value,
            'activo' => $request->boolean('activo', true),
            'id_producto' => $request->validated('id_producto'),
            'categoria' => $request->validated('categoria') ?? CategoriaIsp::SoloTv->value,
            ...$this->datosEmpresa($request),
        ]);
        $this->guardarLogo($request, $isp);

        return redirect()->route('isps.index')->with('success', 'ISP creado correctamente.');
    }

    public function update(UpdateIspRequest $request, Isp $isp): RedirectResponse
    {
        $this->authorize('update', $isp);

        // No cambiamos el tipo (Principal sigue siendo Principal).
        $isp->update([
            'nombre' => $request->validated('nombre'),
            'activo' => $request->boolean('activo'),
            'id_producto' => $request->validated('id_producto'),
            // Solo aplica a ISP cliente (la principal siempre tiene gestión completa).
            'categoria' => $isp->esPrincipal()
                ? $isp->categoria
                : ($request->validated('categoria') ?? $isp->categoria),
            ...$this->datosEmpresa($request),
        ]);
        $this->guardarLogo($request, $isp);

        return redirect()->route('isps.index')->with('success', 'ISP actualizado correctamente.');
    }

    /**
     * Logo de la ISP (vista previa en el formulario).
     */
    public function logo(Isp $isp): HttpResponse
    {
        $this->authorize('update', $isp);

        $logo = $isp->logo()->firstOrFail();

        return response($logo->contenido, 200, [
            'Content-Type' => $logo->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * NIT, dirección y teléfono: encabezado del comprobante de pago.
     *
     * @return array<string, string|null>
     */
    private function datosEmpresa(\Illuminate\Foundation\Http\FormRequest $request): array
    {
        return [
            'nit' => $request->validated('nit') ?: null,
            'direccion' => $request->validated('direccion') ?: null,
            'telefono' => $request->validated('telefono') ?: null,
        ];
    }

    private function guardarLogo(Request $request, Isp $isp): void
    {
        if ($request->hasFile('logo')) {
            IspLogo::guardarPara($isp, $request->file('logo'));
        } elseif ($request->boolean('quitar_logo')) {
            $isp->logo()->delete();
        }
    }

    public function destroy(Isp $isp): RedirectResponse
    {
        $this->authorize('delete', $isp);

        // El ISP Principal no se puede eliminar.
        if ($isp->tipo === TipoIsp::Principal) {
            return back()->with('error', 'El ISP Principal no se puede eliminar.');
        }

        $isp->delete();

        return redirect()->route('isps.index')->with('success', 'ISP eliminado correctamente.');
    }
}
