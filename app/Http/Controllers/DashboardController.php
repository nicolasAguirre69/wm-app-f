<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): Response
    {
        // El Super Admin ve estadísticas globales; el usuario ISP, las suyas.
        $datos = $request->user()->is_super_admin
            ? $dashboard->paraSuperAdmin()
            : $dashboard->paraIsp();

        return Inertia::render('dashboard', $datos);
    }
}
