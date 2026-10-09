<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalización a 3FN.
 *
 *  1. Los datos de la PERSONA salen de `clientes` a la nueva tabla `titulares`
 *     (una persona por ISP + identificación; puede tener varios servicios).
 *  2. `clientes.ciudad_id` se elimina: la ciudad se obtiene del barrio.
 *  3. FKs compuestas (isp_id, x_id): un cliente no puede apuntar a un barrio,
 *     plan, estado o titular de otro ISP (en MySQL/MariaDB).
 *
 * Si la base ya viene normalizada (por ejemplo, al importar wmc_prod_3fn.sql,
 * que ya registra esta migración), no hace nada.
 */
return new class extends Migration
{
    /** Campos de la persona que se mueven de `clientes` a `titulares`. */
    private const CAMPOS = [
        'tipo_identificacion', 'identificacion', 'tipo_contribuyente',
        'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido',
        'telefono_1', 'telefono_2', 'correo',
    ];

    public function up(): void
    {
        if (Schema::hasTable('titulares')) {
            return;
        }

        $esMysql = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        // ------------------------------------------------------------ 1. titulares
        Schema::create('titulares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isps')->cascadeOnDelete();
            $table->string('tipo_identificacion', 5);
            $table->string('identificacion', 20);
            $table->string('tipo_contribuyente', 20);
            $table->string('primer_nombre')->comment('Razón social si es NIT');
            $table->string('segundo_nombre')->nullable();
            $table->string('primer_apellido')->nullable();
            $table->string('segundo_apellido')->nullable();
            $table->string('telefono_1', 20)->nullable();
            $table->string('telefono_2', 20)->nullable();
            $table->string('correo')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['isp_id', 'identificacion']);
            $table->unique(['isp_id', 'id']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedBigInteger('titular_id')->nullable()->after('isp_id');
        });

        // ------------------------------------------------------------ 2. pasar los datos
        // Se agrupa por ISP + identificación; los datos de la persona se toman
        // del servicio actualizado más recientemente.
        $filas = DB::table('clientes')
            ->orderBy('isp_id')->orderBy('updated_at')->orderBy('id')
            ->get(['id', 'isp_id', 'created_at', 'updated_at', ...self::CAMPOS]);

        $grupos = [];
        foreach ($filas as $fila) {
            $clave = $fila->isp_id.'|'.mb_substr(trim((string) $fila->identificacion), 0, 20);
            $grupos[$clave][] = $fila;
        }

        foreach ($grupos as $grupo) {
            $ultimo = end($grupo);

            $titularId = DB::table('titulares')->insertGetId([
                'isp_id' => $ultimo->isp_id,
                'tipo_identificacion' => mb_substr((string) $ultimo->tipo_identificacion, 0, 5),
                'identificacion' => mb_substr(trim((string) $ultimo->identificacion), 0, 20),
                'tipo_contribuyente' => mb_substr((string) $ultimo->tipo_contribuyente, 0, 20),
                'primer_nombre' => $ultimo->primer_nombre,
                'segundo_nombre' => $ultimo->segundo_nombre,
                'primer_apellido' => $this->nulo($ultimo->primer_apellido),
                'segundo_apellido' => $this->nulo($ultimo->segundo_apellido),
                'telefono_1' => $this->telefono($ultimo->telefono_1),
                'telefono_2' => $this->telefono($ultimo->telefono_2),
                'correo' => $this->nulo($ultimo->correo),
                'created_at' => collect($grupo)->min('created_at'),
                'updated_at' => $ultimo->updated_at,
            ]);

            DB::table('clientes')
                ->whereIn('id', array_map(fn ($f) => $f->id, $grupo))
                ->update(['titular_id' => $titularId]);
        }

        // ------------------------------------------------------------ 3. quitar columnas viejas
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign(['ciudad_id']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([...self::CAMPOS, 'ciudad_id']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedBigInteger('titular_id')->nullable(false)->change();
        });

        if (! $esMysql) {
            // SQLite (tests): FK simple, sin compuestas ni CHECK.
            Schema::table('clientes', function (Blueprint $table) {
                $table->foreign('titular_id')->references('id')->on('titulares');
            });

            return;
        }

        // ------------------------------------------------------------ 4. FKs compuestas (MySQL/MariaDB)
        foreach (['barrios', 'planes', 'estados_cliente', 'clientes'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $t) => $t->unique(['isp_id', 'id']));
        }

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign(['barrio_id']);
            $table->dropForeign(['plan_id']);
            $table->dropForeign(['estado_id']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->foreign(['isp_id', 'titular_id'], 'clientes_titular_foreign')->references(['isp_id', 'id'])->on('titulares');
            $table->foreign(['isp_id', 'barrio_id'], 'clientes_barrio_foreign')->references(['isp_id', 'id'])->on('barrios');
            $table->foreign(['isp_id', 'plan_id'], 'clientes_plan_foreign')->references(['isp_id', 'id'])->on('planes');
            $table->foreign(['isp_id', 'estado_id'], 'clientes_estado_foreign')->references(['isp_id', 'id'])->on('estados_cliente');
        });

        Schema::table('comentarios', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
        });
        Schema::table('comentarios', function (Blueprint $table) {
            $table->foreign(['isp_id', 'cliente_id'], 'comentarios_cliente_foreign')
                ->references(['isp_id', 'id'])->on('clientes')->cascadeOnDelete();
        });

        Schema::table('redes', function (Blueprint $table) {
            $table->dropForeign(['barrio_id']);
        });
        Schema::table('redes', function (Blueprint $table) {
            $table->foreign(['isp_id', 'barrio_id'], 'redes_barrio_isp_foreign')
                ->references(['isp_id', 'id'])->on('barrios')->cascadeOnDelete();
        });

        // ------------------------------------------------------------ 5. reglas de dominio (CHECK)
        DB::statement("ALTER TABLE titulares ADD CONSTRAINT titulares_tipo_identificacion_check
            CHECK (tipo_identificacion IN ('CC','CE','NIT','PA','TI','PPT','PEP'))");
        DB::statement("ALTER TABLE titulares ADD CONSTRAINT titulares_tipo_contribuyente_check
            CHECK (tipo_contribuyente IN ('natural','regimen_comun','juridica','gran_contribuyente','regimen_simple','no_responsable_iva'))");
        DB::statement("ALTER TABLE titulares ADD CONSTRAINT titulares_telefono_check
            CHECK ((telefono_1 IS NULL OR telefono_1 REGEXP '^[0-9]{7,11}$') AND (telefono_2 IS NULL OR telefono_2 REGEXP '^[0-9]{7,11}$'))");
        DB::statement('ALTER TABLE clientes ADD CONSTRAINT clientes_dia_corte_check
            CHECK (dia_corte IS NULL OR dia_corte BETWEEN 1 AND 31)');
        DB::statement('ALTER TABLE clientes ADD CONSTRAINT clientes_motivo_check
            CHECK (facturable = 0 OR motivo_no_facturable IS NULL)');
    }

    /**
     * Revierte: devuelve los datos de la persona y la ciudad a `clientes`.
     */
    public function down(): void
    {
        if (! Schema::hasTable('titulares')) {
            return;
        }

        $esMysql = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        if ($esMysql) {
            foreach (['titulares_tipo_identificacion_check', 'titulares_tipo_contribuyente_check', 'titulares_telefono_check'] as $check) {
                DB::statement("ALTER TABLE titulares DROP CONSTRAINT IF EXISTS {$check}");
            }
            foreach (['clientes_dia_corte_check', 'clientes_motivo_check'] as $check) {
                DB::statement("ALTER TABLE clientes DROP CONSTRAINT IF EXISTS {$check}");
            }

            Schema::table('redes', function (Blueprint $table) {
                $table->dropForeign('redes_barrio_isp_foreign');
                $table->foreign('barrio_id')->references('id')->on('barrios');
            });
            Schema::table('comentarios', function (Blueprint $table) {
                $table->dropForeign('comentarios_cliente_foreign');
                $table->foreign('cliente_id')->references('id')->on('clientes')->cascadeOnDelete();
            });
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropForeign('clientes_titular_foreign');
                $table->dropForeign('clientes_barrio_foreign');
                $table->dropForeign('clientes_plan_foreign');
                $table->dropForeign('clientes_estado_foreign');
                $table->foreign('barrio_id')->references('id')->on('barrios')->restrictOnDelete();
                $table->foreign('plan_id')->references('id')->on('planes')->restrictOnDelete();
                $table->foreign('estado_id')->references('id')->on('estados_cliente')->restrictOnDelete();
            });
        } else {
            Schema::table('clientes', fn (Blueprint $t) => $t->dropForeign(['titular_id']));
        }

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('tipo_identificacion')->default('CC');
            $table->string('identificacion')->default('');
            $table->string('tipo_contribuyente')->default('natural');
            $table->string('primer_nombre')->default('');
            $table->string('segundo_nombre')->nullable();
            $table->string('primer_apellido')->default('');
            $table->string('segundo_apellido')->nullable();
            $table->string('telefono_1')->default('');
            $table->string('telefono_2')->nullable();
            $table->string('correo')->nullable();
            $table->unsignedBigInteger('ciudad_id')->nullable();
        });

        foreach (DB::table('titulares')->get() as $titular) {
            DB::table('clientes')->where('titular_id', $titular->id)->update([
                'tipo_identificacion' => $titular->tipo_identificacion,
                'identificacion' => $titular->identificacion,
                'tipo_contribuyente' => $titular->tipo_contribuyente,
                'primer_nombre' => $titular->primer_nombre,
                'segundo_nombre' => $titular->segundo_nombre,
                'primer_apellido' => $titular->primer_apellido ?? '',
                'segundo_apellido' => $titular->segundo_apellido,
                'telefono_1' => $titular->telefono_1 ?? '',
                'telefono_2' => $titular->telefono_2,
                'correo' => $titular->correo,
            ]);
        }

        // La ciudad vuelve a copiarse desde el barrio.
        foreach (DB::table('barrios')->get(['id', 'ciudad_id']) as $barrio) {
            DB::table('clientes')->where('barrio_id', $barrio->id)->update(['ciudad_id' => $barrio->ciudad_id]);
        }

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('titular_id');
            $table->foreign('ciudad_id')->references('id')->on('ciudades')->restrictOnDelete();
        });

        Schema::dropIfExists('titulares');
    }

    private function nulo(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor === '' || strtoupper($valor) === 'N/A' ? null : $valor;
    }

    /** Solo dígitos; si no queda un número válido (7 a 11 dígitos), null. */
    private function telefono(?string $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $valor);

        return strlen($digitos) >= 7 && strlen($digitos) <= 11 ? $digitos : null;
    }
};
