<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Converts uploaded raster images to resized WebP only (no JPG/PNG originals kept).
 */
final class WebpImageConverter
{
    public function store(
        UploadedFile $file,
        string $directory,
        int $maxWidth,
        int $maxHeight,
        int $quality = 82,
        string $attribute = 'image',
    ): string {
        $source = $this->createImageResource($file, $attribute);

        try {
            $resized = $this->resize($source, $maxWidth, $maxHeight, $attribute);

            try {
                $binary = $this->encodeWebp($resized, $quality);
            } finally {
                imagedestroy($resized);
            }
        } finally {
            imagedestroy($source);
        }

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.webp';

        if (! Storage::disk('public')->put($path, $binary)) {
            throw new RuntimeException('No se pudo guardar la imagen WebP.');
        }

        return $path;
    }

    private function createImageResource(UploadedFile $file, string $attribute): \GdImage
    {
        $absolutePath = $file->getRealPath();

        if ($absolutePath === false) {
            throw ValidationException::withMessages([
                $attribute => 'No se pudo leer el archivo de imagen.',
            ]);
        }

        $mime = (string) ($file->getMimeType() ?? '');

        $image = match (true) {
            str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => @imagecreatefromjpeg($absolutePath),
            str_contains($mime, 'png') => @imagecreatefrompng($absolutePath),
            str_contains($mime, 'webp') => @imagecreatefromwebp($absolutePath),
            str_contains($mime, 'gif') => @imagecreatefromgif($absolutePath),
            default => false,
        };

        if ($image === false) {
            throw ValidationException::withMessages([
                $attribute => 'El formato de imagen no es válido. Usa JPG, PNG o WebP.',
            ]);
        }

        return $image;
    }

    private function resize(\GdImage $source, int $maxWidth, int $maxHeight, string $attribute): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width < 1 || $height < 1) {
            throw ValidationException::withMessages([
                $attribute => 'La imagen no tiene dimensiones válidas.',
            ]);
        }

        $scale = min($maxWidth / $width, $maxHeight / $height, 1.0);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        if ($targetWidth === $width && $targetHeight === $height) {
            $clone = imagecreatetruecolor($width, $height);

            if ($clone === false) {
                throw new RuntimeException('No se pudo procesar la imagen.');
            }

            $this->preserveTransparency($clone);
            imagecopy($clone, $source, 0, 0, 0, 0, $width, $height);

            return $clone;
        }

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($resized === false) {
            throw new RuntimeException('No se pudo redimensionar la imagen.');
        }

        $this->preserveTransparency($resized);
        imagecopyresampled(
            $resized,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height,
        );

        return $resized;
    }

    private function preserveTransparency(\GdImage $image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);

        if ($transparent !== false) {
            imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
        }

        imagealphablending($image, true);
    }

    private function encodeWebp(\GdImage $image, int $quality): string
    {
        if (! function_exists('imagewebp')) {
            throw new RuntimeException('PHP GD no tiene soporte WebP habilitado.');
        }

        ob_start();
        $ok = imagewebp($image, null, max(0, min(100, $quality)));
        $binary = ob_get_clean();

        if ($ok === false || ! is_string($binary) || $binary === '') {
            throw new RuntimeException('No se pudo convertir la imagen a WebP.');
        }

        return $binary;
    }
}
