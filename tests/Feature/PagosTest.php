<?php

namespace Tests\Feature;

use App\Models\Barrio;
use App\Models\Ciudad;
use App\Models\Cliente;
use App\Models\EstadoCliente;
use App\Models\Isp;
use App\Models\Pago;
use App\Models\Plan;
use App\Models\TipoPlan;
use App\Models\TipoServicio;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Pagos y comprobantes: solo ISP principal y "Gestión completa", solo
 * servicios Activos, un pago vigente por mes, número consecutivo por ISP,
 * anulación con motivo y aislamiento entre ISP.
 */
class PagosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        TipoPlan::create(['nombre' => 'Hogar']);
        TipoServicio::create(['nombre' => 'TV']);
    }

    /**
     * @return array{isp: Isp, user: User, servicio: Cliente}
     */
    private function crearIsp(string $nombre, bool $gestionCompleta = true): array
    {
        $isp = Isp::create(['nombre' => $nombre, 'tipo' => 'cliente', 'activo' => true]);
        if ($gestionCompleta) {
            $isp->update(['categoria' => 'gestion_completa']);
        }

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

        $ciudad = Ciudad::firstOrCreate(['nombre' => 'Bogotá']);
        $barrio = Barrio::create([
            'isp_id' => $isp->id, 'ciudad_id' => $ciudad->id,
            'nombre' => "Barrio {$isp->id}", 'prefijo' => 'B'.$isp->id,
        ]);
        $plan = Plan::create([
            'isp_id' => $isp->id,
            'tipo_plan_id' => TipoPlan::first()->id,
            'tipo_servicio_id' => TipoServicio::first()->id,
            'valor' => 45000, 'activo' => true,
        ]);

        $servicio = Cliente::crearConTitular([
            'isp_id' => $isp->id,
            'codigo_cliente' => 'S-'.$isp->id,
            'tipo_identificacion' => 'CC',
            'identificacion' => '8'.$isp->id,
            'primer_nombre' => 'Ana',
            'primer_apellido' => 'Gómez',
            'telefono_1' => '3001234567',
            'ciudad_id' => $ciudad->id,
            'barrio_id' => $barrio->id,
            'direccion' => 'Calle 2',
            'plan_id' => $plan->id,
            'estado_id' => EstadoCliente::where('isp_id', $isp->id)->where('nombre', 'Activo')->value('id'),
        ]);

        return ['isp' => $isp, 'user' => $user->fresh(), 'servicio' => $servicio];
    }

    /**
     * @param  array{isp: Isp, user: User, servicio: Cliente}  $ctx
     */
    private function pagar(array $ctx, string $periodo)
    {
        return $this->actingAs($ctx['user'])->postJson("/clientes/{$ctx['servicio']->hashid}/pagos", [
            'periodo' => $periodo,
            'valor' => 45000,
            'fecha_pago' => now()->toDateString(),
            'medio_pago' => 'efectivo',
        ]);
    }

    public function test_una_isp_solo_tv_no_registra_pagos(): void
    {
        $a = $this->crearIsp('ISP TV', gestionCompleta: false);

        $this->actingAs($a['user'])->getJson("/clientes/{$a['servicio']->hashid}/pagos")->assertForbidden();
        $this->pagar($a, now()->format('Y-m'))->assertForbidden();
    }

    public function test_registra_pagos_con_numero_consecutivo_y_uno_por_mes(): void
    {
        $a = $this->crearIsp('ISP A');
        $mes = now()->format('Y-m');

        $this->actingAs($a['user'])->getJson("/clientes/{$a['servicio']->hashid}/pagos")
            ->assertOk()
            ->assertJsonPath('activo', true)
            ->assertJsonPath('sugerido.periodo', $mes)
            ->assertJsonPath('sugerido.valor', '45000');

        $this->pagar($a, $mes)->assertCreated()->assertJsonPath('pago.numero', 'RC-000001');

        // El mismo mes no se paga dos veces.
        $this->pagar($a, $mes)->assertStatus(422)->assertJsonValidationErrors('periodo');

        // El siguiente mes sí, con el siguiente número; y se sugiere el mes siguiente.
        $siguiente = now()->addMonthNoOverflow()->format('Y-m');
        $this->pagar($a, $siguiente)->assertCreated()->assertJsonPath('pago.numero', 'RC-000002');

        $this->actingAs($a['user'])->getJson("/clientes/{$a['servicio']->hashid}/pagos")
            ->assertJsonCount(2, 'pagos')
            ->assertJsonPath('sugerido.periodo', now()->addMonthsNoOverflow(2)->format('Y-m'));

        // Guarda el plan del momento del pago.
        $this->assertSame('Hogar - TV', Pago::withoutGlobalScopes()->first()->plan);
    }

    public function test_solo_se_registran_pagos_de_servicios_activos(): void
    {
        $a = $this->crearIsp('ISP A');
        $a['servicio']->update([
            'estado_id' => EstadoCliente::where('isp_id', $a['isp']->id)->where('nombre', 'Retirado')->value('id'),
        ]);

        $this->actingAs($a['user'])->getJson("/clientes/{$a['servicio']->hashid}/pagos")->assertJsonPath('activo', false);
        $this->pagar($a, now()->format('Y-m'))->assertStatus(422)->assertJsonValidationErrors('periodo');
        $this->assertSame(0, Pago::withoutGlobalScopes()->count());
    }

    public function test_anular_exige_motivo_y_libera_el_mes(): void
    {
        $a = $this->crearIsp('ISP A');
        $mes = now()->format('Y-m');
        $hashid = $this->pagar($a, $mes)->json('pago.hashid');

        $this->actingAs($a['user'])->postJson("/pagos/{$hashid}/anular", ['motivo' => ''])->assertStatus(422);
        $this->actingAs($a['user'])->postJson("/pagos/{$hashid}/anular", ['motivo' => 'Valor errado'])
            ->assertOk()
            ->assertJsonPath('pago.anulado', true);

        // No se borra: queda anulado, y el mes se puede registrar de nuevo.
        $this->assertSame(1, Pago::withoutGlobalScopes()->whereNotNull('anulado_at')->count());
        $this->pagar($a, $mes)->assertCreated()->assertJsonPath('pago.numero', 'RC-000002');

        // Ya anulado no se anula otra vez.
        $this->actingAs($a['user'])->postJson("/pagos/{$hashid}/anular", ['motivo' => 'x'])->assertStatus(422);
    }

    public function test_otra_isp_no_ve_ni_usa_los_pagos_ajenos(): void
    {
        $a = $this->crearIsp('ISP A');
        $b = $this->crearIsp('ISP B');
        $hashid = $this->pagar($a, now()->format('Y-m'))->json('pago.hashid');

        // Numeración independiente por ISP.
        $this->pagar($b, now()->format('Y-m'))->assertJsonPath('pago.numero', 'RC-000001');

        $this->actingAs($b['user'])->getJson("/clientes/{$a['servicio']->hashid}/pagos")->assertNotFound();
        $this->actingAs($b['user'])->get("/pagos/{$hashid}/comprobante")->assertNotFound();
        $this->actingAs($b['user'])->postJson("/pagos/{$hashid}/anular", ['motivo' => 'x'])->assertNotFound();
    }

    public function test_genera_el_comprobante_en_pdf(): void
    {
        $a = $this->crearIsp('ISP A');
        $a['isp']->update(['nit' => '900123456-7', 'direccion' => 'Calle 1 # 2-3', 'telefono' => '6015551234']);
        $hashid = $this->pagar($a, now()->format('Y-m'))->json('pago.hashid');

        $respuesta = $this->actingAs($a['user'])->get("/pagos/{$hashid}/comprobante");

        $respuesta->assertOk();
        $this->assertStringStartsWith('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }
}
