<?php

namespace App\Models;

use App\Traits\BelongsToIsp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

/**
 * Documento digitalizado de un servicio (contrato, cédula...), guardado DENTRO
 * de la base (columna `contenido`, MEDIUMBLOB). Uno por servicio.
 *
 * El contenido nunca viaja en el JSON hacia el navegador ($hidden); se
 * descarga solo por la ruta protegida /clientes/{cliente}/documento.
 */
class DocumentoCliente extends Model
{
    use BelongsToIsp;

    protected $table = 'documentos_cliente';

    protected $fillable = ['isp_id', 'cliente_id', 'nombre_archivo', 'mime', 'tamano', 'contenido'];

    protected $hidden = ['contenido'];

    protected function casts(): array
    {
        return ['tamano' => 'integer'];
    }

    /**
     * Guarda (o reemplaza) el documento de un servicio a partir del archivo subido.
     */
    public static function guardarPara(Cliente $cliente, UploadedFile $archivo): self
    {
        $contenido = (string) file_get_contents($archivo->getRealPath());

        return static::withoutGlobalScopes()->updateOrCreate(
            ['cliente_id' => $cliente->id],
            [
                'isp_id' => $cliente->isp_id,
                'nombre_archivo' => $archivo->getClientOriginalName() ?: 'documento.'.$archivo->extension(),
                'mime' => $archivo->getMimeType() ?: 'application/octet-stream',
                'tamano' => strlen($contenido),
                'contenido' => $contenido,
            ],
        );
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
