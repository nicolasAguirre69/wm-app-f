<?php

namespace Tests\Feature;

use App\Models\Barrio;
use App\Models\Ciudad;
use App\Models\Cliente;
use App\Models\EstadoCliente;
use App\Models\Isp;
use App\Models\Plan;
use App\Models\Ticket;
use App\Models\TipoFalla;
use App\Models\TipoPlan;
use App\Models\TipoServicio;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tickets de soporte: solo ISP principal y "Gestión completa", número
 * consecutivo por ISP, historial de eventos y aislamiento entre ISP.
 */
class TicketsTest extends TestCase
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
     * ISP con Administrador, barrio, plan y un servicio.
     *
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
            'valor' => 50000, 'activo' => true,
        ]);

        $servicio = Cliente::crearConTitular([
            'isp_id' => $isp->id,
            'codigo_cliente' => 'S-'.$isp->id,
            'tipo_identificacion' => 'CC',
            'identificacion' => '9'.$isp->id,
            'primer_nombre' => 'Juan',
            'primer_apellido' => 'Pérez',
            'telefono_1' => '3001234567',
            'ciudad_id' => $ciudad->id,
            'barrio_id' => $barrio->id,
            'direccion' => 'Calle 1',
            'plan_id' => $plan->id,
            'estado_id' => EstadoCliente::where('isp_id', $isp->id)->where('nombre', 'Activo')->value('id'),
        ]);

        return ['isp' => $isp, 'user' => $user->fresh(), 'servicio' => $servicio];
    }

    /**
     * @param  array{isp: Isp, user: User, servicio: Cliente}  $ctx
     */
    private function abrirTicket(array $ctx, string $prioridad = 'media'): Ticket
    {
        $tipo = TipoFalla::withoutGlobalScopes()->where('isp_id', $ctx['isp']->id)->firstOrFail();

        $this->actingAs($ctx['user'])
            ->post('/tickets', [
                'servicio' => $ctx['servicio']->codigo_cliente,
                'tipo_falla_id' => $tipo->id,
                'prioridad' => $prioridad,
                'descripcion' => 'No hay señal desde ayer.',
                'fecha_visita' => now()->addDay()->format('Y-m-d H:i'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return Ticket::withoutGlobalScopes()->where('isp_id', $ctx['isp']->id)->latest('id')->firstOrFail();
    }

    public function test_una_isp_solo_tv_no_tiene_tickets(): void
    {
        $a = $this->crearIsp('ISP TV', gestionCompleta: false);

        $this->assertSame(0, TipoFalla::withoutGlobalScopes()->where('isp_id', $a['isp']->id)->count());
        $this->withoutVite()->actingAs($a['user'])->get('/tickets')->assertForbidden();
        $this->actingAs($a['user'])->post('/tickets', ['servicio' => $a['servicio']->codigo_cliente])->assertForbidden();
    }

    public function test_el_ticket_se_crea_desde_las_opciones_del_servicio_en_clientes(): void
    {
        $tv = $this->crearIsp('ISP TV', gestionCompleta: false);
        $a = $this->crearIsp('ISP A');

        // Solo TV: Clientes no ofrece crear tickets.
        $this->withoutVite()->actingAs($tv['user'])->get('/clientes')
            ->assertInertia(fn (Assert $p) => $p->where('tickets', null));

        // Gestión completa: Clientes trae los tipos de falla de su ISP.
        $this->withoutVite()->actingAs($a['user'])->get('/clientes')
            ->assertInertia(fn (Assert $p) => $p
                ->has('tickets.tiposFalla', count(TipoFalla::POR_DEFECTO))
                ->has('tickets.prioridades'));

        // Al crear el ticket se vuelve a Clientes.
        $this->actingAs($a['user'])->from('/clientes')->post('/tickets', [
            'servicio' => $a['servicio']->hashid,
            'tipo_falla_id' => TipoFalla::withoutGlobalScopes()->where('isp_id', $a['isp']->id)->value('id'),
            'prioridad' => 'alta',
            'descripcion' => 'Sin internet.',
        ])->assertSessionHasNoErrors()->assertRedirect('/clientes');

        $this->assertSame(1, $a['servicio']->tickets()->count());
    }

    public function test_gestion_completa_crea_tickets_con_numero_consecutivo_e_historial(): void
    {
        $a = $this->crearIsp('ISP A');

        // Al pasar a "Gestión completa" recibe los tipos de falla por defecto.
        $this->assertSame(count(TipoFalla::POR_DEFECTO), TipoFalla::withoutGlobalScopes()->where('isp_id', $a['isp']->id)->count());

        $this->withoutVite()->actingAs($a['user'])->get('/tickets')->assertOk();

        $t1 = $this->abrirTicket($a);
        $t2 = $this->abrirTicket($a, 'urgente');

        $this->assertSame(1, $t1->numero);
        $this->assertSame(2, $t2->numero);
        $this->assertTrue($t1->estaAbierto());
        $this->assertSame(['creado'], $t1->eventos()->pluck('tipo')->all());

        // Comentario y cambio de prioridad quedan en el historial.
        $this->actingAs($a['user'])->post("/tickets/{$t1->hashid}/comentarios", ['contenido' => 'Se llamó al cliente.'])->assertSessionHasNoErrors();
        $this->actingAs($a['user'])->put("/tickets/{$t1->hashid}", [
            'tipo_falla_id' => $t1->tipo_falla_id,
            'prioridad' => 'alta',
            'fecha_visita' => $t1->fecha_visita?->format('Y-m-d H:i'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(['creado', 'comentario', 'cambio'], $t1->eventos()->pluck('tipo')->all());
        $this->assertStringContainsString('Prioridad: Media → Alta', $t1->eventos()->where('tipo', 'cambio')->value('contenido'));

        // El servicio muestra su ticket abierto en Clientes.
        $this->assertSame(2, $a['servicio']->tickets()->where('estado', 'abierto')->count());
    }

    public function test_cerrar_exige_solucion_y_se_puede_reabrir(): void
    {
        $a = $this->crearIsp('ISP A');
        $ticket = $this->abrirTicket($a);

        $this->actingAs($a['user'])->post("/tickets/{$ticket->hashid}/cerrar", ['solucion' => ''])->assertSessionHasErrors('solucion');
        $this->assertTrue($ticket->fresh()->estaAbierto());

        $this->actingAs($a['user'])->post("/tickets/{$ticket->hashid}/cerrar", ['solucion' => 'Se cambió el conector.'])->assertSessionHasNoErrors();
        $cerrado = $ticket->fresh();
        $this->assertFalse($cerrado->estaAbierto());
        $this->assertSame($a['user']->id, $cerrado->cerrado_por);
        $this->assertNotNull($cerrado->cerrado_at);

        // Cerrado no admite cambios hasta reabrirlo.
        $this->actingAs($a['user'])->post("/tickets/{$ticket->hashid}/cerrar", ['solucion' => 'Otra vez'])->assertSessionHasErrors('ticket');

        $this->actingAs($a['user'])->post("/tickets/{$ticket->hashid}/reabrir", ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->actingAs($a['user'])->post("/tickets/{$ticket->hashid}/reabrir", ['motivo' => 'Volvió la falla.'])->assertSessionHasNoErrors();

        $this->assertTrue($ticket->fresh()->estaAbierto());
        $this->assertSame(['creado', 'cerrado', 'reabierto'], $ticket->eventos()->pluck('tipo')->all());
    }

    public function test_otra_isp_no_ve_ni_usa_los_tickets_ajenos(): void
    {
        $a = $this->crearIsp('ISP A');
        $b = $this->crearIsp('ISP B');
        $ticket = $this->abrirTicket($a);

        // Numeración independiente por ISP.
        $this->assertSame(1, $this->abrirTicket($b)->numero);

        $this->withoutVite()->actingAs($b['user'])->get("/tickets/{$ticket->hashid}")->assertNotFound();
        $this->actingAs($b['user'])->post("/tickets/{$ticket->hashid}/comentarios", ['contenido' => 'x'])->assertNotFound();

        // Tampoco puede abrir un ticket sobre un servicio de otra ISP.
        $tipoB = TipoFalla::withoutGlobalScopes()->where('isp_id', $b['isp']->id)->firstOrFail();
        $this->actingAs($b['user'])->post('/tickets', [
            'servicio' => $a['servicio']->codigo_cliente,
            'tipo_falla_id' => $tipoB->id,
            'prioridad' => 'media',
            'descripcion' => 'x',
        ])->assertSessionHasErrors('servicio');
    }
}
