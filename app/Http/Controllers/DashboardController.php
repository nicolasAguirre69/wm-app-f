<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(DashboardService $dashboard): Response
    {
        return Inertia::render('dashboard', $dashboard->paraIsp());
    }
}
