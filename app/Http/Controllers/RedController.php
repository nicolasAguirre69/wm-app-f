<?php

namespace App\Http\Controllers;

use App\Http\Requests\Red\StoreRedRequest;
use App\Http\Requests\Red\UpdateRedRequest;
use App\Models\Barrio;
use App\Models\Red;
use App\Services\RedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RedController extends Controller
{
    public function __construct(private RedService $redService)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Red::class);

        $redes = $this->redService->listar(
            $request->only('search', 'sort', 'direction')
        );

        return Inertia::render('redes/index', [
            'redes' => $redes,
            'filtros' => $request->only('search', 'sort', 'direction'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Red::class);

        return Inertia::render('redes/create', [
            'barrios' => $this->barriosDelIsp(),
        ]);
    }

    public function store(StoreRedRequest $request): RedirectResponse
    {
        $this->authorize('create', Red::class);

        $this->redService->crear($request->validated());

        return redirect()
            ->route('redes.index')
            ->with('success', 'Red creada correctamente.');
    }

    public function edit(Red $red): Response
    {
        $this->authorize('update', $red);

        return Inertia::render('redes/edit', [
            'red' => $red,
            'barrios' => $this->barriosDelIsp(),
        ]);
    }

    public function update(UpdateRedRequest $request, Red $red): RedirectResponse
    {
        $this->authorize('update', $red);

        $this->redService->actualizar($red, $request->validated());

        return redirect()
            ->route('redes.index')
            ->with('success', 'Red actualizada correctamente.');
    }

    public function destroy(Red $red): RedirectResponse
    {
        $this->authorize('delete', $red);

        $this->redService->eliminar($red);

        return redirect()
            ->route('redes.index')
            ->with('success', 'Red eliminada correctamente.');
    }

    /**
     * Barrios del ISP actual para el selector de formulario.
     * Incluye el prefijo para poder previsualizar el nombre completo de la red.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, nombre: string, prefijo: string}>
     */
    private function barriosDelIsp()
    {
        return Barrio::with('ciudad')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Barrio $barrio) => [
                'id' => $barrio->id,
                'nombre' => $barrio->nombre.' ('.($barrio->ciudad->nombre ?? '—').')',
                'prefijo' => $barrio->prefijo,
            ]);
    }
}
