<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonIconUploader;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TaxonIconUploaderTest extends TestCase
{
    /**
     * Binary content of a 1x1 PNG image, detected as "image/png" by the file info layer.
     */
    private const PNG_CONTENT = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private string $projectDir;

    protected function setUp(): void
    {
        $projectDir = sys_get_temp_dir() . '/advanced-taxon-' . uniqid('', true);
        self::assertTrue(mkdir($projectDir . '/public', 0775, true));

        $this->projectDir = $projectDir;
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    public function test_it_stores_the_pictogram_in_the_application_public_directory(): void
    {
        $uploader = new TaxonIconUploader($this->projectDir);
        $path = $uploader->upload($this->createUploadedFile('pictogram.png', self::PNG_CONTENT));

        self::assertIsString($path);
        self::assertStringStartsWith(TaxonIconUploader::PUBLIC_PREFIX . '/', $path);
        self::assertStringEndsWith('.png', $path);
        self::assertFileExists($this->projectDir . '/public' . $path);
    }

    public function test_it_creates_the_pictogram_directory_lazily(): void
    {
        $uploader = new TaxonIconUploader($this->projectDir);

        self::assertDirectoryDoesNotExist($uploader->getDirectory());

        $uploader->upload($this->createUploadedFile('pictogram.png', self::PNG_CONTENT));

        self::assertDirectoryExists($uploader->getDirectory());
    }

    /**
     * The extension is derived from the file content, never from the uploaded file name.
     */
    public function test_it_never_stores_a_pictogram_with_an_unexpected_extension(): void
    {
        $uploader = new TaxonIconUploader($this->projectDir);

        $path = $uploader->upload($this->createUploadedFile('pictogram.txt', 'not an image'));

        self::assertIsString($path);
        self::assertStringEndsWith('.png', $path);
        self::assertFileExists($this->projectDir . '/public' . $path);
    }

    public function test_it_generates_a_unique_name_for_each_upload(): void
    {
        $uploader = new TaxonIconUploader($this->projectDir);

        $first = $uploader->upload($this->createUploadedFile('pictogram.png', self::PNG_CONTENT));
        $second = $uploader->upload($this->createUploadedFile('pictogram.png', self::PNG_CONTENT));

        self::assertNotSame($first, $second);
    }

    /**
     * SVG is intentionally rejected: pictograms are served as static assets, and an uploaded SVG
     * could embed scripts (stored XSS on the shop origin).
     */
    public function test_it_never_stores_a_pictogram_with_an_svg_extension(): void
    {
        $uploader = new TaxonIconUploader($this->projectDir);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $path = $uploader->upload($this->createUploadedFile('pictogram.svg', $svg));

        self::assertIsString($path);
        self::assertStringEndsWith('.png', $path);
    }

    public function test_it_rejects_an_oversized_pictogram(): void
    {
        $uploader = new TaxonIconUploader($this->projectDir);

        $sourcePath = tempnam(sys_get_temp_dir(), 'at_icon_big_');
        if ($sourcePath === false) {
            self::fail('Unable to create a temporary file.');
        }

        file_put_contents($sourcePath, str_repeat('a', 2 * 1024 * 1024 + 1));

        $path = $uploader->upload(new UploadedFile($sourcePath, 'pictogram.png', null, null, true));

        self::assertNull($path);
    }

    private function createUploadedFile(string $name, string $content): UploadedFile
    {
        $sourcePath = tempnam(sys_get_temp_dir(), 'at_icon_');
        if ($sourcePath === false) {
            self::fail('Unable to create a temporary file.');
        }

        file_put_contents($sourcePath, base64_decode($content, true) ?: $content);

        return new UploadedFile($sourcePath, $name, null, null, true);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if (!$item instanceof SplFileInfo) {
                continue;
            }

            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
