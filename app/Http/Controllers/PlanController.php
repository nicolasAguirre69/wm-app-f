<?php

namespace App\Http\Controllers;

use App\Http\Requests\Plan\StorePlanRequest;
use App\Http\Requests\Plan\UpdatePlanRequest;
use App\Models\Plan;
use App\Models\TipoPlan;
use App\Models\TipoServicio;
use App\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function __construct(private PlanService $planService)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Plan::class);

        $planes = $this->planService->listar(
            $request->only('search', 'sort', 'direction')
        );

        return Inertia::render('planes/index', [
            'planes' => $planes,
            'filtros' => $request->only('search', 'sort', 'direction'),
            ...$this->catalogos(),
        ]);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        $this->authorize('create', Plan::class);

        $this->planService->crear($request->validated());

        return redirect()
            ->route('planes.index')
            ->with('success', 'Plan creado correctamente.');
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);

        $this->planService->actualizar($plan, $request->validated());

        return redirect()
            ->route('planes.index')
            ->with('success', 'Plan actualizado correctamente.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $this->authorize('delete', $plan);

        $this->planService->eliminar($plan);

        return redirect()
            ->route('planes.index')
            ->with('success', 'Plan eliminado correctamente.');
    }

    /**
     * Catálogos globales para los selectores del formulario.
     *
     * @return array{tiposPlan: \Illuminate\Support\Collection<int, TipoPlan>, tiposServicio: \Illuminate\Support\Collection<int, TipoServicio>}
     */
    private function catalogos(): array
    {
        return [
            'tiposPlan' => TipoPlan::orderBy('nombre')->get(['id', 'nombre']),
            'tiposServicio' => TipoServicio::orderBy('nombre')->get(['id', 'nombre']),
        ];
    }
}
