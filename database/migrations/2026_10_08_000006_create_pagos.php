<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pagos de los servicios y su comprobante en PDF (ISP principal y
 * "Gestión completa").
 *
 *   isps        + nit, direccion, telefono: encabezado del comprobante.
 *   isp_logos   logo de cada ISP, guardado en la base (MEDIUMBLOB).
 *   pagos       un pago por servicio y mes (periodo), número consecutivo por
 *               ISP. No se borra: se ANULA con motivo (trazabilidad).
 *
 * También crea los permisos pagos.* y se los da a los roles existentes.
 */
return new class extends Migration
{
    /** Permiso => roles que lo reciben (además de Administrador, que recibe todos). */
    private const PERMISOS = [
        'pagos.ver' => ['Facturación', 'Atención al Cliente'],
        'pagos.registrar' => ['Facturación', 'Atención al Cliente'],
        'pagos.anular' => ['Facturación'],
    ];

    public function up(): void
    {
        $esMysql = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        Schema::table('isps', function (Blueprint $table) {
            $table->string('nit', 20)->nullable()->after('nombre');
            $table->string('direccion', 150)->nullable()->after('nit');
            $table->string('telefono', 30)->nullable()->after('direccion');
        });

        Schema::create('isp_logos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->unique()->constrained('isps')->cascadeOnDelete();
            $table->string('mime', 50);
            $table->binary('contenido');
            $table->timestamps();
        });

        if ($esMysql) {
            DB::statement('ALTER TABLE isp_logos MODIFY contenido MEDIUMBLOB NOT NULL');
        }

        Schema::create('pagos', function (Blueprint $table) use ($esMysql) {
            $table->id();
            $table->unsignedBigInteger('isp_id');
            $table->unsignedInteger('numero')->comment('Consecutivo del comprobante por ISP');
            $table->unsignedBigInteger('cliente_id')->comment('Servicio');
            $table->date('periodo')->comment('Mes pagado (día 1 del mes)');
            $table->decimal('valor', 12, 2);
            $table->date('fecha_pago');
            $table->string('medio_pago', 20);
            $table->string('referencia', 60)->nullable();
            $table->string('observacion', 500)->nullable();
            // Datos del servicio al momento del pago (el comprobante no cambia
            // aunque luego cambie el plan).
            $table->string('plan', 120)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion', 500)->nullable();
            $table->timestamps();

            $table->unique(['isp_id', 'numero']);
            $table->index(['cliente_id', 'periodo']);
            $table->index(['isp_id', 'periodo']);

            $table->foreign('isp_id')->references('id')->on('isps')->cascadeOnDelete();

            if ($esMysql) {
                // El servicio debe ser del mismo ISP del pago.
                $table->foreign(['isp_id', 'cliente_id'], 'pagos_cliente_foreign')
                    ->references(['isp_id', 'id'])->on('clientes')->cascadeOnDelete();
            } else {
                $table->foreign('cliente_id')->references('id')->on('clientes')->cascadeOnDelete();
            }
        });

        if ($esMysql) {
            DB::statement("ALTER TABLE pagos ADD CONSTRAINT pagos_medio_check CHECK (medio_pago IN ('efectivo','transferencia','consignacion','tarjeta','otro'))");
            DB::statement('ALTER TABLE pagos ADD CONSTRAINT pagos_valor_check CHECK (valor > 0)');
            DB::statement('ALTER TABLE pagos ADD CONSTRAINT pagos_anulacion_check CHECK (anulado_at IS NULL OR motivo_anulacion IS NOT NULL)');
        }

        $this->asignarPermisos();
    }

    private function asignarPermisos(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId(null);

        foreach (array_keys(self::PERMISOS) as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }
        $registrar->forgetCachedPermissions();

        foreach (Role::all() as $rol) {
            $permisos = $rol->name === 'Administrador'
                ? array_keys(self::PERMISOS)
                : array_keys(array_filter(self::PERMISOS, fn ($roles) => in_array($rol->name, $roles, true)));

            if ($permisos) {
                $rol->givePermissionTo($permisos); // se suman a los que ya tenía
            }
        }

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('isp_logos');

        Schema::table('isps', function (Blueprint $table) {
            $table->dropColumn(['nit', 'direccion', 'telefono']);
        });

        if (Schema::hasTable('permissions')) {
            Permission::whereIn('name', array_keys(self::PERMISOS))->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
