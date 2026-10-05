<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Stores taxon pictograms in the application public directory.
 *
 * A pictogram is a plain file, not a Sylius image resource: it is written under
 * "<application>/public/media/advanced-taxon/icons" and the taxon stores its web path
 * ("/media/advanced-taxon/icons/<file>"), so it can be rendered with the Symfony asset helper
 * like any other public asset.
 *
 * Writing inside the application public directory is what makes the pictogram reachable: the
 * plugin own `public/` directory is not published by `assets:install`.
 */
final class TaxonIconUploader
{
    /**
     * Web path prefix of the pictograms, relative to the application public directory.
     *
     * The canonical value lives on the taxon model so that input sanitization and storage agree.
     */
    public const PUBLIC_PREFIX = Taxon::ICON_PATH_PREFIX;

    /**
     * Extensions accepted for a pictogram upload. Any other extension falls back to PNG so that a
     * pictogram can never be written with an arbitrary extension.
     *
     * SVG is deliberately excluded: pictograms are served as static assets from the application
     * public directory, and a user provided SVG can embed scripts, which would be a stored XSS on
     * the shop origin.
     */
    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

    /**
     * Upper bound for a pictogram upload, in bytes.
     */
    private const MAX_SIZE_BYTES = 2 * 1024 * 1024;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * Moves the uploaded file into the public media directory and returns the path to store on the
     * taxon, or null when the target directory could not be created.
     */
    public function upload(UploadedFile $file): ?string
    {
        $size = $file->getSize();
        if ($size !== false && $size > self::MAX_SIZE_BYTES) {
            return null;
        }

        $directory = $this->getDirectory();

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }

        $extension = (string) $file->guessExtension();
        if (!in_array(strtolower($extension), self::ALLOWED_EXTENSIONS, true)) {
            $extension = 'png';
        }

        $filename = sprintf('%s.%s', uniqid('pictogram_', true), $extension);
        $file->move($directory, $filename);

        return self::PUBLIC_PREFIX . '/' . $filename;
    }

    /**
     * Absolute path of the pictogram directory, exposed for diagnostics and tests.
     */
    public function getDirectory(): string
    {
        return rtrim($this->projectDir, '/') . '/public' . self::PUBLIC_PREFIX;
    }
}
