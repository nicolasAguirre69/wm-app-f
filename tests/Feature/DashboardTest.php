<?php

namespace Tests\Feature;

use App\Models\Isp;
use App\Models\TipoPlan;
use App\Models\TipoServicio;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        // Todo usuario pertenece a una ISP activa (sin ella, el middleware
        // isp.active lo saca con "Tu ISP ha sido desactivado").
        // Catálogos que el ISP necesita al crearse (plan de TV automático).
        $this->seed(PermissionSeeder::class);
        TipoPlan::create(['nombre' => 'Hogar']);
        TipoServicio::create(['nombre' => 'TV']);
        $isp = Isp::create(['nombre' => 'ISP Prueba', 'tipo' => 'cliente', 'activo' => true]);

        $this->actingAs(User::factory()->create(['isp_id' => $isp->id, 'activo' => true]));

        $this->withoutVite()->get('/dashboard')->assertOk();
    }
}
