<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Logo de una ISP, guardado dentro de la base (MEDIUMBLOB). Se usa en el
 * encabezado de los comprobantes de pago. Está en su propia tabla para no
 * cargar la imagen cada vez que se consulta una ISP.
 */
class IspLogo extends Model
{
    protected $table = 'isp_logos';

    protected $fillable = ['isp_id', 'mime', 'contenido'];

    protected $hidden = ['contenido'];

    /** Ancho máximo con el que se guarda el logo (suficiente para el PDF). */
    private const ANCHO_MAXIMO = 800;

    public static function guardarPara(Isp $isp, UploadedFile $archivo): self
    {
        $contenido = (string) file_get_contents($archivo->getRealPath());
        $mime = $archivo->getMimeType() ?: 'image/png';

        // Se recortan los bordes transparentes y se reduce el tamaño, para que
        // el PDF se genere rápido. Si no hay GD, se guarda tal cual.
        if ($optimizado = self::optimizar($contenido)) {
            [$contenido, $mime] = [$optimizado, 'image/jpeg'];
        }

        return static::updateOrCreate(
            ['isp_id' => $isp->id],
            ['mime' => $mime, 'contenido' => $contenido],
        );
    }

    private static function optimizar(string $contenido): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $imagen = @imagecreatefromstring($contenido);
        if (! $imagen) {
            return null;
        }

        imagesavealpha($imagen, true);

        if (function_exists('imagecropauto') && ($recortada = imagecropauto($imagen, IMG_CROP_TRANSPARENT))) {
            $imagen = $recortada;
        }

        if (imagesx($imagen) > self::ANCHO_MAXIMO) {
            $imagen = imagescale($imagen, self::ANCHO_MAXIMO) ?: $imagen;
        }

        // Se pone sobre el azul del encabezado del comprobante y se guarda como
        // JPG: así el PDF lo dibuja aunque el servidor no tenga GD.
        $fondo = imagecreatetruecolor(imagesx($imagen), imagesy($imagen));
        imagefill($fondo, 0, 0, imagecolorallocate($fondo, 0x0b, 0x1f, 0x3a));
        imagealphablending($fondo, true);
        imagecopy($fondo, $imagen, 0, 0, 0, 0, imagesx($imagen), imagesy($imagen));

        ob_start();
        imagejpeg($fondo, null, 90);

        return ob_get_clean() ?: null;
    }

    /** La imagen como data URI (para incrustarla en el PDF). */
    public function dataUri(): string
    {
        return 'data:'.$this->mime.';base64,'.base64_encode($this->contenido);
    }
}
