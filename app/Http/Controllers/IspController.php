<?php

namespace App\Http\Controllers;

use App\Enums\TipoIsp;
use App\Http\Requests\Isp\StoreIspRequest;
use App\Http\Requests\Isp\UpdateIspRequest;
use App\Models\Isp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IspController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Isp::class);

        $isps = Isp::withCount(['clientes', 'users'])
            ->when(
                $request->filled('search'),
                fn ($q) => $q->where('nombre', 'like', '%'.$request->string('search').'%')
            )
            ->orderByDesc('tipo') // principal primero
            ->orderBy('nombre')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Isp $isp) => [
                'id' => $isp->id,
                'nombre' => $isp->nombre,
                'tipo' => $isp->tipo->value,
                'tipo_label' => $isp->tipo->label(),
                'activo' => $isp->activo,
                'id_producto' => $isp->id_producto,
                'clientes_count' => $isp->clientes_count,
                'users_count' => $isp->users_count,
                'es_principal' => $isp->tipo === TipoIsp::Principal,
            ]);

        return Inertia::render('isps/index', [
            'isps' => $isps,
            'filtros' => $request->only('search'),
        ]);
    }

    public function store(StoreIspRequest $request): RedirectResponse
    {
        $this->authorize('create', Isp::class);

        // Desde la interfaz siempre se crean ISPs de tipo Cliente.
        Isp::create([
            'nombre' => $request->validated('nombre'),
            'tipo' => TipoIsp::Cliente->value,
            'activo' => $request->boolean('activo', true),
            'id_producto' => $request->validated('id_producto'),
        ]);

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
        ]);

        return redirect()->route('isps.index')->with('success', 'ISP actualizado correctamente.');
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
