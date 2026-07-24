<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ciudad\StoreCiudadRequest;
use App\Http\Requests\Ciudad\UpdateCiudadRequest;
use App\Models\Ciudad;
use App\Services\CiudadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CiudadController extends Controller
{
    /**
     * Inyección de dependencias: Laravel construye y pasa el CiudadService.
     */
    public function __construct(private CiudadService $ciudadService)
    {
    }

    /**
     * Listado con búsqueda, orden y paginación.
     */
    public function index(Request $request): Response
    {
        // Autorización explícita: usa CiudadPolicy@viewAny.
        $this->authorize('viewAny', Ciudad::class);

        $ciudades = $this->ciudadService->listar(
            $request->only('search', 'sort', 'direction')
        );

        return Inertia::render('ciudades/index', [
            'ciudades' => $ciudades,
            'filtros' => $request->only('search', 'sort', 'direction'),
        ]);
    }

    /**
     * Formulario de creación.
     */
    public function create(): Response
    {
        $this->authorize('create', Ciudad::class);

        return Inertia::render('ciudades/create');
    }

    /**
     * Guarda una ciudad nueva.
     */
    public function store(StoreCiudadRequest $request): RedirectResponse
    {
        $this->authorize('create', Ciudad::class);

        $this->ciudadService->crear($request->validated());

        return redirect()
            ->route('ciudades.index')
            ->with('success', 'Ciudad creada correctamente.');
    }

    /**
     * Formulario de edición.
     */
    public function edit(Ciudad $ciudad): Response
    {
        $this->authorize('update', $ciudad);

        return Inertia::render('ciudades/edit', [
            'ciudad' => $ciudad,
        ]);
    }

    /**
     * Actualiza una ciudad.
     */
    public function update(UpdateCiudadRequest $request, Ciudad $ciudad): RedirectResponse
    {
        $this->authorize('update', $ciudad);

        $this->ciudadService->actualizar($ciudad, $request->validated());

        return redirect()
            ->route('ciudades.index')
            ->with('success', 'Ciudad actualizada correctamente.');
    }

    /**
     * Elimina (soft delete) una ciudad.
     */
    public function destroy(Ciudad $ciudad): RedirectResponse
    {
        $this->authorize('delete', $ciudad);

        $this->ciudadService->eliminar($ciudad);

        return redirect()
            ->route('ciudades.index')
            ->with('success', 'Ciudad eliminada correctamente.');
    }
}
