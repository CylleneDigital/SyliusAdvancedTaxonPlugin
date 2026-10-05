<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use Sylius\Component\Core\Model\Image;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores taxon pictograms with the Sylius image uploader, in the same storage as the other Sylius
 * images (local, S3, ...). The taxon keeps "image:<path in the storage>" as its icon.
 */
final readonly class TaxonIconUploader
{
    /**
     * Content types accepted for a pictogram, checked on the file content.
     *
     * SVG is deliberately excluded: a user provided SVG can embed scripts, which would be a stored
     * XSS once the file is served from the shop origin.
     */
    private const array ALLOWED_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

    /**
     * Upper bound for a pictogram upload, in bytes.
     */
    private const MAX_SIZE_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private ImageUploaderInterface $imageUploader,
    ) {
    }

    /**
     * Stores the uploaded pictogram and returns the icon value to store on the taxon, or null when
     * the file is not an acceptable image.
     */
    public function upload(UploadedFile $file): ?string
    {
        $size = $file->getSize();
        if ($size === false || $size > self::MAX_SIZE_BYTES) {
            return null;
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
            return null;
        }

        $image = new class() extends Image {
        };
        $image->setFile($file);
        $this->imageUploader->upload($image);

        $path = $image->getPath();
        if ($path === null) {
            return null;
        }

        return AdvancedTaxonInterface::ICON_IMAGE_PREFIX . $path;
    }

    /**
     * Removes an uploaded pictogram from the storage; a glyph icon is left alone.
     */
    public function remove(?string $icon): void
    {
        $path = self::storagePath($icon);
        if ($path !== null) {
            $this->imageUploader->remove($path);
        }
    }

    /**
     * Path of an uploaded pictogram in the Sylius image storage, null for a glyph icon.
     */
    public static function storagePath(?string $icon): ?string
    {
        if ($icon === null || !str_starts_with($icon, AdvancedTaxonInterface::ICON_IMAGE_PREFIX)) {
            return null;
        }

        return substr($icon, strlen(AdvancedTaxonInterface::ICON_IMAGE_PREFIX));
    }
}
