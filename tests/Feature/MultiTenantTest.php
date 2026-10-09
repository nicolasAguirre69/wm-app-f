<?php

namespace Tests\Feature;

use App\Models\Barrio;
use App\Models\Ciudad;
use App\Models\Cliente;
use App\Models\EstadoCliente;
use App\Models\Isp;
use App\Models\Plan;
use App\Models\Red;
use App\Models\Titular;
use App\Models\TipoPlan;
use App\Models\TipoServicio;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    private function crearCliente(array $ctx, string $codigo, string $identificacion = '123456'): Cliente
    {
        // Persona (titular) + servicio: reutiliza el titular si la cédula ya existe.
        return Cliente::crearConTitular([
            'isp_id' => $ctx['isp']->id,
            'codigo_cliente' => $codigo,
            'tipo_identificacion' => 'CC',
            'identificacion' => $identificacion,
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

    public function test_al_crear_un_isp_se_generan_5_roles_y_sus_estados(): void
    {
        // ISP cliente: por norma solo Activo y Retirado.
        $cliente = $this->crearIspCompleto('ISP X');
        $this->assertSame(5, Role::where('team_id', $cliente['isp']->id)->count());
        $this->assertEqualsCanonicalizing(
            ['Activo', 'Retirado'],
            EstadoCliente::where('isp_id', $cliente['isp']->id)->pluck('nombre')->all(),
        );

        // ISP principal: los 4 estados estándar.
        $principal = $this->crearIspCompleto('ISP P', 'principal');
        $this->assertSame(4, EstadoCliente::where('isp_id', $principal['isp']->id)->count());
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
            ->patch("/clientes/{$cliente->hashid}/facturable", ['facturable' => true])
            ->assertForbidden();

        $this->assertFalse($cliente->fresh()->facturable);
    }

    public function test_el_super_admin_si_puede_cambiar_facturable(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $cliente = $this->crearCliente($a, 'A-1');

        $this->actingAs($this->crearSuperAdmin($a['isp']->id))
            ->patch("/clientes/{$cliente->hashid}/facturable", ['facturable' => true])
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

    public function test_una_persona_con_dos_servicios_es_un_solo_titular_en_la_lista(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $this->crearCliente($a, 'A-1', '999');
        $this->crearCliente($a, 'A-2', '999');
        $this->crearCliente($a, 'A-3', '888');

        $this->assertSame(2, Titular::withoutGlobalScopes()->count());

        $this->withoutVite()
            ->actingAs($a['user'])
            ->get('/clientes')
            ->assertOk()
            ->assertInertia(fn ($pagina) => $pagina
                ->component('clientes/index')
                ->has('titulares.data', 2)
                ->where('totalServicios', 3)
            );
    }

    public function test_agregar_un_servicio_a_un_titular_existente(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $titular = $this->crearCliente($a, 'A-1', '999')->titular;

        $this->actingAs($a['user'])
            ->post('/clientes', [
                'titular' => $titular->hashid,
                // Aunque lleguen datos de la persona, se ignoran.
                'primer_nombre' => 'Otro Nombre',
                'codigo_cliente' => 'A-2',
                'ciudad_id' => $a['ciudad']->id,
                'barrio_id' => $a['barrio']->id,
                'direccion' => 'Calle 2',
                'estado_id' => $a['estado']->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $titular->refresh();
        $this->assertSame(2, $titular->clientes()->count());
        $this->assertSame('Juan', $titular->primer_nombre);
    }

    public function test_cliente_nuevo_con_cedula_existente_pide_agregar_servicio(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $this->crearCliente($a, 'A-1', '999');

        $this->actingAs($a['user'])
            ->post('/clientes', [
                'codigo_cliente' => 'A-2',
                'tipo_identificacion' => 'CC',
                'identificacion' => '999',
                'primer_nombre' => 'Juan',
                'primer_apellido' => 'Pérez',
                'telefono_1' => '3001234567',
                'ciudad_id' => $a['ciudad']->id,
                'barrio_id' => $a['barrio']->id,
                'direccion' => 'Calle 2',
                'estado_id' => $a['estado']->id,
            ])
            ->assertSessionHasErrors('identificacion');
    }

    public function test_editar_el_titular_cambia_los_datos_en_todos_sus_servicios(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $uno = $this->crearCliente($a, 'A-1', '999');
        $dos = $this->crearCliente($a, 'A-2', '999');

        $this->actingAs($a['user'])
            ->put("/titulares/{$uno->titular->hashid}", [
                'tipo_identificacion' => 'CC',
                'identificacion' => '999',
                'primer_nombre' => 'maría',
                'primer_apellido' => 'gómez',
                'telefono_1' => '310 555 1234',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('María', $dos->fresh()->primer_nombre);
        $this->assertSame('3105551234', $dos->fresh()->telefono_1);
    }

    public function test_la_isp_principal_puede_marcar_puerto_alquilado(): void
    {
        Storage::fake('public');
        $p = $this->crearIspCompleto('ISP P', 'principal');

        $this->actingAs($p['user'])
            ->post('/clientes', [
                'codigo_cliente' => 'P-1',
                'tipo_identificacion' => 'CC',
                'identificacion' => '777',
                'primer_nombre' => 'Ana',
                'primer_apellido' => 'Ruiz',
                'telefono_1' => '3001234567',
                'ciudad_id' => $p['ciudad']->id,
                'barrio_id' => $p['barrio']->id,
                'direccion' => 'Calle 3',
                'plan_id' => $p['plan']->id,
                'estado_id' => $p['estado']->id,
                'puerto_alquilado' => '1',
                'documento_digitalizado' => UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Cliente::where('codigo_cliente', 'P-1')->first()->puerto_alquilado);
    }

    public function test_una_isp_cliente_nunca_tiene_puerto_alquilado(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $titular = $this->crearCliente($a, 'A-1', '999')->titular;

        $this->actingAs($a['user'])
            ->post('/clientes', [
                'titular' => $titular->hashid,
                'codigo_cliente' => 'A-2',
                'ciudad_id' => $a['ciudad']->id,
                'barrio_id' => $a['barrio']->id,
                'direccion' => 'Calle 2',
                'estado_id' => $a['estado']->id,
                'puerto_alquilado' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(Cliente::where('codigo_cliente', 'A-2')->first()->puerto_alquilado);
    }

    public function test_en_la_isp_principal_el_codigo_no_se_repite_ni_con_eliminados(): void
    {
        Storage::fake('public');
        $p = $this->crearIspCompleto('ISP P', 'principal');
        $this->crearCliente($p, 'P-1', '111');
        $this->crearCliente($p, 'P-2', '222')->delete(); // servicio eliminado

        $datos = fn (string $codigo, string $cedula) => [
            'codigo_cliente' => $codigo,
            'tipo_identificacion' => 'CC',
            'identificacion' => $cedula,
            'primer_nombre' => 'Ana',
            'primer_apellido' => 'Ruiz',
            'telefono_1' => '3001234567',
            'ciudad_id' => $p['ciudad']->id,
            'barrio_id' => $p['barrio']->id,
            'direccion' => 'Calle 3',
            'plan_id' => $p['plan']->id,
            'estado_id' => $p['estado']->id,
            'documento_digitalizado' => UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf'),
        ];

        // Código de un servicio activo.
        $this->actingAs($p['user'])->post('/clientes', $datos('P-1', '333'))
            ->assertSessionHasErrors('codigo_cliente');

        // Código de un servicio eliminado: error de validación, no error 500.
        $this->actingAs($p['user'])->post('/clientes', $datos('P-2', '444'))
            ->assertSessionHasErrors('codigo_cliente');
    }

    public function test_el_documento_se_guarda_en_la_base_y_solo_lo_ve_su_isp(): void
    {
        Storage::fake('public');
        $p = $this->crearIspCompleto('ISP P', 'principal');
        $otra = $this->crearIspCompleto('ISP B');

        $this->actingAs($p['user'])
            ->post('/clientes', [
                'codigo_cliente' => 'P-9',
                'tipo_identificacion' => 'CC',
                'identificacion' => '999',
                'primer_nombre' => 'Ana',
                'primer_apellido' => 'Ruiz',
                'telefono_1' => '3001234567',
                'ciudad_id' => $p['ciudad']->id,
                'barrio_id' => $p['barrio']->id,
                'direccion' => 'Calle 9',
                'plan_id' => $p['plan']->id,
                'estado_id' => $p['estado']->id,
                'documento_digitalizado' => UploadedFile::fake()->createWithContent('contrato.pdf', '%PDF-1.4 prueba'),
            ])
            ->assertSessionHasNoErrors();

        $cliente = Cliente::where('codigo_cliente', 'P-9')->firstOrFail();

        // Quedó en la base, no en el disco.
        $this->assertSame('%PDF-1.4 prueba', $cliente->documento->contenido);
        $this->assertSame([], Storage::disk('public')->allFiles());

        // Su ISP lo puede abrir...
        $respuesta = $this->actingAs($p['user'])->get("/clientes/{$cliente->hashid}/documento");
        $respuesta->assertOk();
        $this->assertSame('%PDF-1.4 prueba', $respuesta->getContent());

        // ...otra ISP no.
        $this->actingAs($otra['user'])->get("/clientes/{$cliente->hashid}/documento")->assertNotFound();
    }

    public function test_una_isp_de_gestion_completa_elige_plan_y_maneja_corte(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $a['isp']->update(['categoria' => 'gestion_completa']);

        // Al pasar a "Gestión completa", la ISP gana el estado Corte.
        $this->assertTrue(EstadoCliente::where('isp_id', $a['isp']->id)->where('nombre', 'Corte')->exists());

        $internet = TipoServicio::create(['nombre' => 'Internet']);
        $planInternet = Plan::create([
            'isp_id' => $a['isp']->id, 'tipo_plan_id' => TipoPlan::first()->id,
            'tipo_servicio_id' => $internet->id, 'cantidad' => 300, 'valor' => 60000, 'activo' => true,
        ]);

        $this->actingAs($a['user'])
            ->post('/clientes', [
                'codigo_cliente' => 'A-50',
                'tipo_identificacion' => 'CC',
                'identificacion' => '5050',
                'primer_nombre' => 'Luis',
                'primer_apellido' => 'Mora',
                'telefono_1' => '3001234567',
                'ciudad_id' => $a['ciudad']->id,
                'barrio_id' => $a['barrio']->id,
                'direccion' => 'Calle 50',
                'plan_id' => $planInternet->id,
                'estado_id' => $a['estado']->id,
            ])
            ->assertSessionHasNoErrors();

        // Se respeta el plan elegido (no se fuerza el de TV).
        $cliente = Cliente::where('codigo_cliente', 'A-50')->firstOrFail();
        $this->assertSame($planInternet->id, $cliente->plan_id);

        // Un clic en el estado: Activo -> Corte.
        $this->actingAs($a['user'])->patch("/clientes/{$cliente->hashid}/estado")->assertRedirect();
        $this->assertSame('Corte', $cliente->fresh()->estado->nombre);
    }

    public function test_en_una_isp_solo_tv_el_plan_sigue_siendo_tv(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $otroPlan = $a['plan']; // plan creado en crearIspCompleto

        $this->actingAs($a['user'])
            ->post('/clientes', [
                'codigo_cliente' => 'A-60',
                'tipo_identificacion' => 'CC',
                'identificacion' => '6060',
                'primer_nombre' => 'Luis',
                'primer_apellido' => 'Mora',
                'telefono_1' => '3001234567',
                'ciudad_id' => $a['ciudad']->id,
                'barrio_id' => $a['barrio']->id,
                'direccion' => 'Calle 60',
                'plan_id' => $otroPlan->id,
                'estado_id' => $a['estado']->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($a['isp']->planTv()->id, Cliente::where('codigo_cliente', 'A-60')->value('plan_id'));
    }

    public function test_la_exportacion_no_incluye_solo_internet_de_una_isp_cliente(): void
    {
        $a = $this->crearIspCompleto('ISP A');
        $a['isp']->update(['categoria' => 'gestion_completa']);
        $internet = TipoServicio::create(['nombre' => 'Internet']);
        $planInternet = Plan::create([
            'isp_id' => $a['isp']->id, 'tipo_plan_id' => TipoPlan::first()->id,
            'tipo_servicio_id' => $internet->id, 'cantidad' => 100, 'valor' => 50000, 'activo' => true,
        ]);

        $tv = $this->crearCliente($a, 'A-TV', '111');
        $soloInternet = $this->crearCliente($a, 'A-NET', '222');
        $tv->update(['facturable' => true]);
        $soloInternet->update(['facturable' => true, 'plan_id' => $planInternet->id]);

        $json = $this->actingAs($this->crearSuperAdmin($a['isp']->id))
            ->get('/clientes/exportar-facturacion')
            ->assertOk()
            ->getContent();

        $ids = array_column(json_decode($json, true), 'clienteIdentificacion');
        $this->assertContains('111', $ids);
        $this->assertNotContains('222', $ids);
    }

    public function test_solo_las_isp_de_gestion_completa_administran_planes(): void
    {
        $a = $this->crearIspCompleto('ISP A'); // Solo TV por defecto

        $this->withoutVite()->actingAs($a['user'])->get('/planes')->assertForbidden();

        $a['isp']->update(['categoria' => 'gestion_completa']);

        $this->withoutVite()->actingAs($a['user']->fresh())->get('/planes')->assertOk();
    }
}
