<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comentarios/observaciones de un cliente. Dos tipos:
     * 'seguimiento' (día a día del ISP) y 'facturacion' (sensibles).
     */
    public function up(): void
    {
        Schema::create('comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isps')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            // Autor del comentario (queda registrado quién lo escribió).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo'); // 'seguimiento' | 'facturacion'
            $table->text('contenido');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cliente_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comentarios');
    }
};
