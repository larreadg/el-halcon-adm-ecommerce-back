<?php

declare(strict_types=1);

class ImageHelper
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_SIDE  = 800;
    private const QUALITY   = 82;
    private const ALLOWED   = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Valida el archivo subido en $_FILES[$field].
     * Devuelve el array del archivo con 'detected_mime' agregado, o null si no es válido.
     */
    public static function fromRequest(string $field = 'imagen'): ?array
    {
        $file = $_FILES[$field] ?? null;

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return null;
        }

        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return null;
        }

        $info = getimagesize($file['tmp_name']);
        $mime = $info ? ($info['mime'] ?? '') : '';

        if (!in_array($mime, self::ALLOWED, true)) {
            return null;
        }

        $file['detected_mime'] = $mime;
        return $file;
    }

    /**
     * Valida que la imagen tenga la proporción esperada (por defecto 16:9) con tolerancia del 6%.
     */
    public static function validateAspectRatio(array $file, float $expected = 16 / 9, float $tolerance = 0.06): bool
    {
        $size = getimagesize($file['tmp_name']);
        if (!$size || (int) $size[1] === 0) {
            return false;
        }
        $ratio = $size[0] / $size[1];
        return abs($ratio - $expected) / $expected <= $tolerance;
    }

    /**
     * Valida que la imagen tenga exactamente las dimensiones indicadas.
     */
    public static function validateDimensions(array $file, int $requiredWidth, int $requiredHeight): bool
    {
        $size = getimagesize($file['tmp_name']);
        if (!$size) {
            return false;
        }
        return (int) $size[0] === $requiredWidth && (int) $size[1] === $requiredHeight;
    }

    /**
     * Procesa y guarda la imagen en uploads/$subdir/.
     * Devuelve ['absolute_path' => ..., 'relative_path' => ...] o null si falla.
     */
    public static function store(array $file, string $subdir, int $maxSide = self::MAX_SIDE): ?array
    {
        $destDir = __DIR__ . '/../../uploads/' . $subdir . '/';

        if (!is_dir($destDir) && !mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            return null;
        }

        $filename = bin2hex(random_bytes(12)) . '.webp';
        $destPath = $destDir . $filename;
        $imgInfo  = getimagesize($file['tmp_name']);
        $mime     = (string) ($file['detected_mime'] ?? ($imgInfo['mime'] ?? ''));

        if (!self::process($file['tmp_name'], $mime, $destPath, $maxSide)) {
            return null;
        }

        return [
            'absolute_path' => $destPath,
            'relative_path' => 'uploads/' . $subdir . '/' . $filename,
        ];
    }

    /**
     * Elimina un archivo de disco a partir de su ruta relativa.
     */
    public static function delete(string $relativePath): void
    {
        $absolutePath = __DIR__ . '/../../' . ltrim($relativePath, '/');

        if (file_exists($absolutePath)) {
            unlink($absolutePath);
        }
    }

    /**
     * Limpia un archivo que fue guardado pero cuya operación posterior falló.
     */
    public static function cleanup(?array $stored): void
    {
        $path = $stored['absolute_path'] ?? null;

        if ($path && file_exists($path)) {
            unlink($path);
        }
    }

    private static function process(string $tmpPath, string $mime, string $destPath, int $maxSide = self::MAX_SIDE): bool
    {
        ini_set('memory_limit', '256M');

        $src = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($tmpPath),
            'image/png'  => imagecreatefrompng($tmpPath),
            'image/webp' => imagecreatefromwebp($tmpPath),
            default      => false,
        };

        if (!$src) {
            return false;
        }

        [$origW, $origH] = getimagesize($tmpPath);

        if ($origW <= $maxSide && $origH <= $maxSide) {
            $newW = $origW;
            $newH = $origH;
        } elseif ($origW >= $origH) {
            $newW = $maxSide;
            $newH = (int) round($origH * $maxSide / $origW);
        } else {
            $newH = $maxSide;
            $newW = (int) round($origW * $maxSide / $origH);
        }

        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        $ok = imagewebp($dst, $destPath, self::QUALITY);

        return $ok;
    }
}
