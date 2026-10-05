<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

use GdImage;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Downscales an uploaded media before Sylius stores it, keeping its format (and so its
 * transparency).
 */
final readonly class UploadedImageResizer
{
    /**
     * Rough memory needed by GD per pixel of a decoded true color image, source and target included.
     */
    private const int BYTES_PER_PIXEL = 10;

    public function __construct(
        private int $maxDimension = 1600,
        private int $maxFilesizeBytes = 2 * 1024 * 1024,
        private int $quality = 82,
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
        if (!$isTooLarge || !$this->canDecode($width, $height)) {
            return;
        }

        $mimeType = $file->getMimeType();
        $source = $this->readImage($path, $mimeType);
        if ($source === null) {
            return;
        }

        $scale = min(1, $this->maxDimension / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($resized === false) {
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
        $written = $this->writeImage($resized, $outputPath, $mimeType);

        if ($written) {
            rename($outputPath, $path);
        } else {
            @unlink($outputPath);
        }
    }

    /**
     * Decoding a huge image would exhaust the memory limit with a fatal error: such a file is kept
     * as is instead.
     */
    private function canDecode(int $width, int $height): bool
    {
        $limit = $this->memoryLimitBytes();

        return $limit === null || memory_get_usage() + $width * $height * self::BYTES_PER_PIXEL < $limit;
    }

    private function memoryLimitBytes(): ?int
    {
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit === '' || $limit === '-1') {
            return null;
        }

        $value = (int) $limit;

        return match (strtolower(substr($limit, -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function readImage(string $path, ?string $mimeType): ?GdImage
    {
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            'image/gif' => imagecreatefromgif($path),
            default => null,
        };

        return $image instanceof GdImage ? $image : null;
    }

    private function writeImage(GdImage $image, string $path, ?string $mimeType): bool
    {
        return match ($mimeType) {
            'image/png' => imagepng($image, $path),
            'image/webp' => imagewebp($image, $path, $this->quality),
            'image/gif' => imagegif($image, $path),
            default => imagejpeg($image, $path, $this->quality),
        };
    }
}
