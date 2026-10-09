<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Los documentos digitalizados de los clientes pasan del disco a la base.
 *
 * Antes: el archivo vivía en storage/app/public/clientes/documentos (carpeta
 * pública: cualquiera con el enlace podía abrirlo) y clientes.documento_digitalizado
 * guardaba la ruta.
 *
 * Ahora: tabla documentos_cliente con el contenido del archivo (MEDIUMBLOB,
 * hasta 16 MB; la app acepta máximo 5 MB). Un documento por servicio. Solo se
 * descarga por la ruta protegida /clientes/{cliente}/documento.
 *
 * Los archivos existentes se copian a la tabla. Los del disco NO se borran
 * (quedan como respaldo; se pueden eliminar a mano después de verificar).
 */
return new class extends Migration
{
    public function up(): void
    {
        $esMysql = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);

        // Reanudable: si un intento anterior falló a medias (MySQL no deshace
        // el CREATE TABLE), la tabla ya existe y se continúa desde ahí.
        if (! Schema::hasTable('documentos_cliente')) {
            Schema::create('documentos_cliente', function (Blueprint $table) use ($esMysql) {
                $table->id();
                $table->unsignedBigInteger('isp_id');
                $table->unsignedBigInteger('cliente_id')->unique(); // uno por servicio
                $table->string('nombre_archivo');
                $table->string('mime', 100);
                $table->unsignedInteger('tamano')->comment('Bytes');
                $table->binary('contenido');
                $table->timestamps();

                $table->foreign('isp_id')->references('id')->on('isps')->cascadeOnDelete();

                if ($esMysql) {
                    // Mismo ISP que el servicio (FK compuesta, como el resto del esquema).
                    $table->foreign(['isp_id', 'cliente_id'], 'documentos_cliente_cliente_foreign')
                        ->references(['isp_id', 'id'])->on('clientes')->cascadeOnDelete();
                } else {
                    $table->foreign('cliente_id')->references('id')->on('clientes')->cascadeOnDelete();
                }
            });
        }

        if ($esMysql) {
            // BLOB normal llega solo a 64 KB: se amplía a MEDIUMBLOB (16 MB).
            DB::statement('ALTER TABLE documentos_cliente MODIFY contenido MEDIUMBLOB NOT NULL');
        }

        // --- Copiar los archivos que ya existen en el disco ---
        $disco = Storage::disk('public');
        $faltantes = [];

        $yaCopiados = DB::table('documentos_cliente')->pluck('cliente_id')->all();

        DB::table('clientes')
            ->whereNotNull('documento_digitalizado')
            ->whereNotIn('id', $yaCopiados)
            ->where('documento_digitalizado', '!=', '')
            ->orderBy('id')
            ->get(['id', 'isp_id', 'codigo_cliente', 'documento_digitalizado'])
            ->each(function ($c) use ($disco, &$faltantes) {
                if (! $disco->exists($c->documento_digitalizado)) {
                    $faltantes[] = "{$c->codigo_cliente} ({$c->documento_digitalizado})";

                    return;
                }

                $contenido = $disco->get($c->documento_digitalizado);

                DB::table('documentos_cliente')->insert([
                    'isp_id' => $c->isp_id,
                    'cliente_id' => $c->id,
                    'nombre_archivo' => basename($c->documento_digitalizado),
                    'mime' => $disco->mimeType($c->documento_digitalizado) ?: 'application/octet-stream',
                    'tamano' => strlen($contenido),
                    'contenido' => $contenido,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        if ($faltantes) {
            Log::warning('Documentos de clientes que no estaban en el disco (no se copiaron): '.implode(', ', $faltantes));
        }

        if (Schema::hasColumn('clientes', 'documento_digitalizado')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('documento_digitalizado');
            });
        }
    }

    /**
     * Devuelve los documentos al disco y la ruta a clientes.documento_digitalizado.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('documento_digitalizado')->nullable();
        });

        $disco = Storage::disk('public');

        DB::table('documentos_cliente')->orderBy('id')->get()->each(function ($d) use ($disco) {
            $extension = pathinfo($d->nombre_archivo, PATHINFO_EXTENSION) ?: 'bin';
            $ruta = 'clientes/documentos/'.Str::random(40).'.'.$extension;

            $disco->put($ruta, $d->contenido);
            DB::table('clientes')->where('id', $d->cliente_id)->update(['documento_digitalizado' => $ruta]);
        });

        Schema::dropIfExists('documentos_cliente');
    }
};
