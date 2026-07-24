<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Aporta authorize() y authorizeResource() a todos los controladores.
    use AuthorizesRequests;
}
