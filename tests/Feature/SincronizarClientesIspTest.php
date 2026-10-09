<?php

namespace Tests\Feature;

use App\Models\Barrio;
use App\Models\Ciudad;
use App\Models\Cliente;
use App\Models\EstadoCliente;
use App\Models\Isp;
use App\Models\TipoPlan;
use App\Models\TipoServicio;
use App\Models\Titular;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SincronizarClientesIspTest extends TestCase
{
    use RefreshDatabase;

    private Isp $isp;

    private Barrio $barrio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        TipoPlan::create(['nombre' => 'Hogar']);
        TipoServicio::create(['nombre' => 'TV']);

        $this->isp = Isp::create(['nombre' => 'Net Bell', 'tipo' => 'cliente', 'activo' => true]);
        $ciudad = Ciudad::firstOrCreate(['nombre' => 'Bogotá']);
        $this->barrio = Barrio::create(['isp_id' => $this->isp->id, 'ciudad_id' => $ciudad->id, 'nombre' => 'JUAN PABLO II', 'prefijo' => 'JP']);
    }

    private function estado(string $nombre): int
    {
        return EstadoCliente::where('isp_id', $this->isp->id)->where('nombre', $nombre)->value('id');
    }

    private function servicio(string $codigo, string $cedula, string $direccion, string $estado = 'Activo'): Cliente
    {
        return Cliente::crearConTitular([
            'isp_id' => $this->isp->id,
            'codigo_cliente' => $codigo,
            'tipo_identificacion' => 'CC',
            'identificacion' => $cedula,
            'primer_nombre' => 'Viejo',
            'primer_apellido' => 'Nombre',
            'telefono_1' => '3000000000',
            'barrio_id' => $this->barrio->id,
            'direccion' => $direccion,
            'plan_id' => $this->isp->planTv()->id,
            'estado_id' => $this->estado($estado),
            'facturable' => $estado === 'Activo',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function archivo(array $filas): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'sync').'.json';
        file_put_contents($ruta, json_encode(['isp' => 'Net Bell', 'filas' => $filas]));

        return $ruta;
    }

    private function fila(int $n, string $cedula, string $direccion, string $estado = 'Activo', string $nombres = 'ANA MARIA', string $apellidos = 'RUIZ GOMEZ'): array
    {
        return [
            'fila' => $n, 'tipo_identificacion' => 'CC', 'identificacion' => $cedula,
            'nombres' => $nombres, 'apellidos' => $apellidos, 'direccion' => $direccion,
            'telefonos' => '3101234567-3207654321', 'barrio' => 'JUAN PABO II', 'correo' => null, 'estado' => $estado,
        ];
    }

    public function test_la_simulacion_no_escribe_nada(): void
    {
        $this->servicio('TV-0001', '111', 'CL 1 # 2-3');

        $this->artisan('clientes:sincronizar', ['archivo' => $this->archivo([$this->fila(2, '999', 'CL 9')])])
            ->assertSuccessful();

        $this->assertSame(1, Cliente::count());
        $this->assertSame($this->estado('Activo'), Cliente::first()->estado_id);
    }

    public function test_el_archivo_manda(): void
    {
        $this->servicio('TV-0001', '111', 'CL 1 # 2-3');                 // sigue activo, se actualiza
        $this->servicio('TV-0002', '222', 'KR 5 10 20');                 // rojo en el archivo
        $this->servicio('TV-0003', '333', 'DG 7 8 9');                   // no está en el archivo
        $this->servicio('TV-0004', '444', 'CL 4', 'Retirado');           // vuelve como activo
        $this->servicio('TV-0005', '555', 'CL 55');                      // 2 servicios en BD...
        $this->servicio('TV-0006', '555', 'CL 55');                      // ...1 fila en el archivo

        $filas = [
            $this->fila(2, '111', 'CALLE 1 # 2-3'),
            $this->fila(3, '222', 'CRA 5 10 20', 'Retirado'),
            $this->fila(4, '444', 'CL 4'),
            $this->fila(5, '555', 'CL 55'),
            $this->fila(6, '666', 'CL 66'),                               // nuevo...
            $this->fila(7, '666', 'CL 66'),                               // ...con 2 servicios
        ];

        $this->artisan('clientes:sincronizar', ['archivo' => $this->archivo($filas), '--aplicar' => true])
            ->assertSuccessful();

        $estado = fn (string $codigo) => Cliente::where('codigo_cliente', $codigo)->value('estado_id');

        $this->assertSame($this->estado('Activo'), $estado('TV-0001'));
        $this->assertSame($this->estado('Retirado'), $estado('TV-0002'));
        $this->assertSame($this->estado('Retirado'), $estado('TV-0003'));
        $this->assertSame($this->estado('Activo'), $estado('TV-0004'));
        $this->assertTrue(Cliente::where('codigo_cliente', 'TV-0004')->value('facturable'));

        // De los 2 servicios iguales de 555 queda 1 activo y 1 retirado.
        $this->assertSame(1, Cliente::whereIn('codigo_cliente', ['TV-0005', 'TV-0006'])->where('estado_id', $this->estado('Activo'))->count());

        // Persona nueva con 2 servicios, códigos siguientes.
        $nueva = Titular::where('identificacion', '666')->firstOrFail();
        $this->assertSame(['TV-0007', 'TV-0008'], $nueva->clientes()->orderBy('codigo_cliente')->pluck('codigo_cliente')->all());

        // Datos de la persona actualizados desde el archivo.
        $ana = Titular::where('identificacion', '111')->firstOrFail();
        $this->assertSame('Ana', $ana->primer_nombre);
        $this->assertSame('Maria', $ana->segundo_nombre);
        $this->assertSame('3101234567', $ana->telefono_1);
        $this->assertSame('3207654321', $ana->telefono_2);
    }
}
