<?php

namespace Tests\Feature;

use App\Models\Barrio;
use App\Models\Ciudad;
use App\Models\Cliente;
use App\Models\EstadoCliente;
use App\Models\Isp;
use App\Models\Plan;
use App\Models\Red;
use App\Models\TipoPlan;
use App\Models\TipoServicio;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MultiTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Permisos y catálogos globales necesarios.
        $this->seed(PermissionSeeder::class);
        TipoPlan::create(['nombre' => 'Hogar']);
        TipoServicio::create(['nombre' => 'TV']);
    }

    /**
     * Crea un ISP con un usuario Administrador y sus catálogos mínimos.
     *
     * @return array<string, mixed>
     */
    private function crearIspCompleto(string $nombre, string $tipo = 'cliente'): array
    {
        $isp = Isp::create(['nombre' => $nombre, 'tipo' => $tipo, 'activo' => true]);

        $user = User::create([
            'name' => "Admin {$nombre}",
            'email' => str($nombre)->slug().'@test.com',
            'password' => bcrypt('password'),
            'isp_id' => $isp->id,
            'is_super_admin' => false,
            'activo' => true,
            'email_verified_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($isp->id);
        $user->assignRole('Administrador');

        $ciudad = Ciudad::firstOrCreate(['nombre' => 'Bogotá']); // global
        $barrio = Barrio::create([
            'isp_id' => $isp->id, 'ciudad_id' => $ciudad->id,
            'nombre' => "Barrio {$isp->id}", 'prefijo' => 'B'.$isp->id,
        ]);
        $plan = Plan::create([
            'isp_id' => $isp->id,
            'tipo_plan_id' => TipoPlan::first()->id,
            'tipo_servicio_id' => TipoServicio::first()->id,
            'valor' => 50000, 'activo' => true,
        ]);
        $estado = EstadoCliente::where('isp_id', $isp->id)->where('nombre', 'Activo')->first();

        return compact('isp', 'user', 'ciudad', 'barrio', 'plan', 'estado');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function crearCliente(array $ctx, string $codigo): Cliente
    {
        return Cliente::create([
            'isp_id' => $ctx['isp']->id,
            'codigo_cliente' => $codigo,
            'tipo_identificacion' => 'CC',
            'identificacion' => '123456',
            'tipo_contribuyente' => 'natural',
            'primer_nombre' => 'Juan',
            'primer_apellido' => 'Pérez',
            'telefono_1' => '3001234567',
            'ciudad_id' => $ctx['ciudad']->id,
            'barrio_id' => $ctx['barrio']->id,
            'direccion' => 'Calle 1',
            'plan_id' => $ctx['plan']->id,
            'estado_id' => $ctx['estado']->id,
        ]);
    }

    private function crearSuperAdmin(int $ispId): User
    {
        return User::create([
            'name' => 'Super', 'email' => 'super@test.com', 'password' => bcrypt('password'),
            'isp_id' => $ispId, 'is_super_admin' => true, 'activo' => true, 'email_verified_at' => now(),
        ]);
    }

    public function test_un_usuario_solo_ve_los_clientes_de_su_isp(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $b = $this->crearIspCompleto('ISP B');
        $this->crearCliente($a, 'A-1');
        $this->crearCliente($b, 'B-1');

        $this->actingAs($a['user']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($a['user']->isp_id);

        $this->assertSame(1, Cliente::count());
        $this->assertSame('A-1', Cliente::first()->codigo_cliente);
    }

    public function test_el_super_admin_ve_los_clientes_de_todas_las_isps(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $b = $this->crearIspCompleto('ISP B');
        $this->crearCliente($a, 'A-1');
        $this->crearCliente($b, 'B-1');

        $this->actingAs($this->crearSuperAdmin($a['isp']->id));

        $this->assertSame(2, Cliente::count());
    }

    public function test_al_crear_un_isp_se_generan_5_roles_y_4_estados(): void
    {
        $ctx = $this->crearIspCompleto('ISP X');

        $this->assertSame(5, Role::where('team_id', $ctx['isp']->id)->count());
        $this->assertSame(4, EstadoCliente::where('isp_id', $ctx['isp']->id)->count());
    }

    public function test_al_crear_un_barrio_se_generan_16_redes(): void
    {
        $ctx = $this->crearIspCompleto('ISP Y');

        $this->assertSame(16, Red::where('barrio_id', $ctx['barrio']->id)->count());
    }

    public function test_un_usuario_normal_no_puede_cambiar_facturable(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $cliente = $this->crearCliente($a, 'A-1');

        $this->actingAs($a['user'])
            ->patch("/clientes/{$cliente->id}/facturable", ['facturable' => true])
            ->assertForbidden();

        $this->assertFalse($cliente->fresh()->facturable);
    }

    public function test_el_super_admin_si_puede_cambiar_facturable(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $cliente = $this->crearCliente($a, 'A-1');

        $this->actingAs($this->crearSuperAdmin($a['isp']->id))
            ->patch("/clientes/{$cliente->id}/facturable", ['facturable' => true])
            ->assertRedirect();

        $this->assertTrue($cliente->fresh()->facturable);
    }

    public function test_el_codigo_de_cliente_puede_repetirse_entre_isps(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $b = $this->crearIspCompleto('ISP B');

        $this->crearCliente($a, 'MISMO-CODIGO');
        $this->crearCliente($b, 'MISMO-CODIGO');

        $this->actingAs($this->crearSuperAdmin($a['isp']->id));

        $this->assertSame(2, Cliente::where('codigo_cliente', 'MISMO-CODIGO')->count());
    }
}
