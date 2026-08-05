<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla `clientes` (POR ISP). El módulo central del sistema.
     */
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isps')->cascadeOnDelete();

            // Código asignado por el usuario (texto libre). Único POR ISP.
            $table->string('codigo_cliente');

            // Identificación (tipo como enum en string).
            $table->string('tipo_identificacion');
            $table->string('identificacion');
            $table->string('tipo_contribuyente');

            // Nombres. Solo segundo_nombre es opcional.
            $table->string('primer_nombre');
            $table->string('segundo_nombre')->nullable();
            $table->string('primer_apellido');
            $table->string('segundo_apellido');

            // Contacto. Solo telefono_2 es opcional.
            $table->string('telefono_1');
            $table->string('telefono_2')->nullable();
            $table->string('correo');

            // Ubicación (catálogos por ISP). restrictOnDelete: no borrar un
            // catálogo si hay clientes usándolo.
            $table->foreignId('ciudad_id')->constrained('ciudades')->restrictOnDelete();
            $table->foreignId('barrio_id')->constrained('barrios')->restrictOnDelete();
            $table->string('direccion');

            // Servicio.
            $table->foreignId('plan_id')->constrained('planes')->restrictOnDelete();
            $table->foreignId('estado_id')->constrained('estados_cliente')->restrictOnDelete();

            $table->date('fecha_instalacion');
            $table->unsignedTinyInteger('dia_corte');

            // Quién lo creó.
            $table->foreignId('usuario_creador_id')->nullable()->constrained('users')->nullOnDelete();

            // Documento digitalizado (ruta del archivo en Storage).
            $table->string('documento_digitalizado')->nullable();

            // Facturación: nace en FALSE; solo el Super Admin lo cambia.
            $table->boolean('facturable')->default(false);
            $table->text('motivo_no_facturable')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Código único DENTRO del ISP (dos ISPs sí pueden repetir código).
            $table->unique(['isp_id', 'codigo_cliente']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
