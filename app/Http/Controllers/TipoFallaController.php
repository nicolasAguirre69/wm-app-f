<?php

namespace App\Http\Controllers;

use App\Models\TipoFalla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de tipos de falla de los tickets (por ISP).
 */
class TipoFallaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TipoFalla::class);

        return Inertia::render('tipos-falla/index', [
            'tipos' => TipoFalla::withoutGlobalScopes()
                ->where('isp_id', $request->user()->isp_id)
                ->withCount('tickets')
                ->orderByDesc('activo')
                ->orderBy('nombre')
                ->get()
                ->map(fn (TipoFalla $t) => [
                    'id' => $t->id,
                    'hashid' => $t->hashid,
                    'nombre' => $t->nombre,
                    'activo' => $t->activo,
                    'tickets' => $t->tickets_count,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', TipoFalla::class);

        $ispId = $request->user()->isp_id;
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:60', Rule::unique('tipos_falla', 'nombre')->where('isp_id', $ispId)],
        ], ['nombre.unique' => 'Ya existe ese tipo de falla.']);

        TipoFalla::create(['isp_id' => $ispId, 'nombre' => trim($datos['nombre']), 'activo' => true]);

        return back()->with('success', 'Tipo de falla creado.');
    }

    public function update(Request $request, TipoFalla $tipoFalla): RedirectResponse
    {
        $this->authorize('update', $tipoFalla);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:60', Rule::unique('tipos_falla', 'nombre')->where('isp_id', $tipoFalla->isp_id)->ignore($tipoFalla->id)],
            'activo' => ['boolean'],
        ], ['nombre.unique' => 'Ya existe ese tipo de falla.']);

        $tipoFalla->update(['nombre' => trim($datos['nombre']), 'activo' => $request->boolean('activo')]);

        return back()->with('success', 'Tipo de falla actualizado.');
    }

    public function destroy(TipoFalla $tipoFalla): RedirectResponse
    {
        $this->authorize('delete', $tipoFalla);

        // Con tickets no se borra (perdería la trazabilidad): se desactiva.
        if ($tipoFalla->tickets()->exists()) {
            return back()->with('error', "\"{$tipoFalla->nombre}\" tiene tickets: desactívelo en lugar de eliminarlo.");
        }

        $tipoFalla->delete();

        return back()->with('success', 'Tipo de falla eliminado.');
    }
}
