<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

use GdImage;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadedImageResizer
{
    public function __construct(
        private readonly int $maxDimension = 1600,
        private readonly int $maxFilesizeBytes = 2 * 1024 * 1024,
        private readonly int $jpegQuality = 82,
    ) {
    }

    public function resizeIfNeeded(File $file): void
    {
        if (!$file instanceof UploadedFile) {
            return;
        }

        $path = $file->getPathname();

        if (!is_file($path)) {
            return;
        }

        $sizeBytes = filesize($path);
        $imageSize = getimagesize($path);

        if ($sizeBytes === false || $imageSize === false) {
            return;
        }

        [$width, $height] = $imageSize;

        $isTooLarge = $sizeBytes > $this->maxFilesizeBytes || $width > $this->maxDimension || $height > $this->maxDimension;
        if (!$isTooLarge) {
            return;
        }

        $source = $this->readImage($file);
        if ($source === null) {
            return;
        }

        $scale = min(1, $this->maxDimension / max($width, $height));
        if ($scale >= 1) {
            $scale = 1;
        }

        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($resized === false) {
            imagedestroy($source);

            return;
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        if ($transparent !== false) {
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $outputPath = $path . '.tmp';
        imagejpeg($resized, $outputPath, $this->jpegQuality);
        imagedestroy($source);
        imagedestroy($resized);

        @unlink($path);
        rename($outputPath, $path);
    }

    private function readImage(File $file): ?GdImage
    {
        $path = $file->getPathname();

        $image = match ($file->getMimeType()) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            'image/gif' => imagecreatefromgif($path),
            default => null,
        };

        return $image instanceof GdImage ? $image : null;
    }
}
