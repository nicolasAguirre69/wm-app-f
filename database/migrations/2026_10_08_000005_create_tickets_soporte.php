<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Módulo de tickets de soporte (ISP principal y "Gestión completa").
 *
 *   tipos_falla     catálogo por ISP (Sin servicio, Lentitud...), editable.
 *   tickets         un ticket por servicio (cliente), número consecutivo por ISP.
 *   ticket_eventos  historial INMUTABLE: creado, comentario, cambio, cerrado,
 *                   reabierto. Es la trazabilidad del soporte.
 *
 * También crea los permisos tickets.* y se los da a los roles existentes.
 */
return new class extends Migration
{
    /** Permiso => roles que lo reciben (además de Administrador, que recibe todos). */
    private const PERMISOS = [
        'tickets.ver' => ['Soporte', 'Atención al Cliente', 'Ventas', 'Facturación'],
        'tickets.crear' => ['Soporte', 'Atención al Cliente'],
        'tickets.comentar' => ['Soporte', 'Atención al Cliente'],
        'tickets.editar' => ['Soporte'],
        'tickets.cerrar' => ['Soporte'],
        'tickets.tipos' => [],
    ];

    /** Tipos de falla iniciales de cada ISP con el módulo. */
    public const TIPOS_FALLA = [
        'Sin servicio', 'Lentitud', 'Intermitencia', 'Sin señal de TV',
        'Daño de equipo', 'Traslado', 'Instalación', 'Otro',
    ];

    public function up(): void
    {
        $esMysql = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        Schema::create('tipos_falla', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isps')->cascadeOnDelete();
            $table->string('nombre', 60);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['isp_id', 'nombre']);
            $table->unique(['isp_id', 'id']);
        });

        Schema::create('tickets', function (Blueprint $table) use ($esMysql) {
            $table->id();
            $table->unsignedBigInteger('isp_id');
            $table->unsignedInteger('numero')->comment('Consecutivo por ISP (#1, #2...)');
            $table->unsignedBigInteger('cliente_id')->comment('Servicio');
            $table->unsignedBigInteger('tipo_falla_id');
            $table->string('prioridad', 10)->default('media');
            $table->string('estado', 10)->default('abierto');
            $table->text('descripcion');
            $table->dateTime('fecha_visita')->nullable();
            $table->text('solucion')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrado_at')->nullable();
            $table->timestamps();

            $table->unique(['isp_id', 'numero']);
            $table->index(['isp_id', 'estado']);
            $table->index('cliente_id');

            $table->foreign('isp_id')->references('id')->on('isps')->cascadeOnDelete();

            if ($esMysql) {
                // El servicio y el tipo de falla deben ser del mismo ISP del ticket.
                $table->foreign(['isp_id', 'cliente_id'], 'tickets_cliente_foreign')
                    ->references(['isp_id', 'id'])->on('clientes')->cascadeOnDelete();
                $table->foreign(['isp_id', 'tipo_falla_id'], 'tickets_tipo_falla_foreign')
                    ->references(['isp_id', 'id'])->on('tipos_falla');
            } else {
                $table->foreign('cliente_id')->references('id')->on('clientes')->cascadeOnDelete();
                $table->foreign('tipo_falla_id')->references('id')->on('tipos_falla');
            }
        });

        Schema::create('ticket_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 20); // creado | comentario | cambio | cerrado | reabierto
            $table->text('contenido')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'id']);
        });

        if ($esMysql) {
            DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_prioridad_check CHECK (prioridad IN ('baja','media','alta','urgente'))");
            DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_estado_check CHECK (estado IN ('abierto','cerrado'))");
            DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_cierre_check CHECK (estado = 'abierto' OR solucion IS NOT NULL)");
            DB::statement("ALTER TABLE ticket_eventos ADD CONSTRAINT ticket_eventos_tipo_check CHECK (tipo IN ('creado','comentario','cambio','cerrado','reabierto'))");
        }

        // --- Tipos de falla iniciales: ISP principal y "Gestión completa" ---
        $isps = DB::table('isps')
            ->where('tipo', 'principal')
            ->when(Schema::hasColumn('isps', 'categoria'), fn ($q) => $q->orWhere('categoria', 'gestion_completa'))
            ->pluck('id');

        foreach ($isps as $ispId) {
            foreach (self::TIPOS_FALLA as $nombre) {
                DB::table('tipos_falla')->insertOrIgnore([
                    'isp_id' => $ispId, 'nombre' => $nombre, 'activo' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // --- Permisos nuevos y asignación a los roles que ya existen ---
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
        Schema::dropIfExists('ticket_eventos');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('tipos_falla');

        if (Schema::hasTable('permissions')) {
            Permission::whereIn('name', array_keys(self::PERMISOS))->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
