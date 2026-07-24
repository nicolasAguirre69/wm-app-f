<?php

namespace App\Http\Controllers;

use App\Http\Requests\Barrio\StoreBarrioRequest;
use App\Http\Requests\Barrio\UpdateBarrioRequest;
use App\Models\Barrio;
use App\Models\Ciudad;
use App\Services\BarrioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BarrioController extends Controller
{
    public function __construct(private BarrioService $barrioService)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Barrio::class);

        $barrios = $this->barrioService->listar(
            $request->only('search', 'sort', 'direction')
        );

        return Inertia::render('barrios/index', [
            'barrios' => $barrios,
            'filtros' => $request->only('search', 'sort', 'direction'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Barrio::class);

        return Inertia::render('barrios/create', [
            'ciudades' => $this->ciudadesDelIsp(),
        ]);
    }

    public function store(StoreBarrioRequest $request): RedirectResponse
    {
        $this->authorize('create', Barrio::class);

        $this->barrioService->crear($request->validated());

        return redirect()
            ->route('barrios.index')
            ->with('success', 'Barrio creado correctamente.');
    }

    public function edit(Barrio $barrio): Response
    {
        $this->authorize('update', $barrio);

        return Inertia::render('barrios/edit', [
            'barrio' => $barrio,
            'ciudades' => $this->ciudadesDelIsp(),
        ]);
    }

    public function update(UpdateBarrioRequest $request, Barrio $barrio): RedirectResponse
    {
        $this->authorize('update', $barrio);

        $this->barrioService->actualizar($barrio, $request->validated());

        return redirect()
            ->route('barrios.index')
            ->with('success', 'Barrio actualizado correctamente.');
    }

    public function destroy(Barrio $barrio): RedirectResponse
    {
        $this->authorize('delete', $barrio);

        $this->barrioService->eliminar($barrio);

        return redirect()
            ->route('barrios.index')
            ->with('success', 'Barrio eliminado correctamente.');
    }

    /**
     * Ciudades del ISP actual para los selectores de formulario.
     * El Global Scope ya las filtra por ISP automáticamente.
     *
     * @return \Illuminate\Support\Collection<int, Ciudad>
     */
    private function ciudadesDelIsp()
    {
        return Ciudad::orderBy('nombre')->get(['id', 'nombre']);
    }
}
