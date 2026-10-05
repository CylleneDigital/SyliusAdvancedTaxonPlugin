<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonIconUploader;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ImageInterface;
use Sylius\Component\Core\Uploader\ImageUploaderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TaxonIconUploaderTest extends TestCase
{
    private const PNG_CONTENT = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\x9cc\xf8\x0f\x00\x00\x01\x01\x00\x05\x18\xd8N\x00\x00\x00\x00IEND\xaeB`\x82";

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
    }

    public function test_it_stores_the_pictogram_with_the_sylius_image_uploader(): void
    {
        $imageUploader = $this->createImageUploader();

        $icon = (new TaxonIconUploader($imageUploader))->upload($this->createUploadedFile('pictogram.png', self::PNG_CONTENT));

        self::assertSame('image:uploaded/1.png', $icon);
        self::assertSame(['uploaded/1.png'], $imageUploader->stored);
    }

    public function test_it_removes_an_uploaded_pictogram_only(): void
    {
        $imageUploader = $this->createImageUploader();
        $uploader = new TaxonIconUploader($imageUploader);

        $uploader->remove('image:previous/icon.png');
        $uploader->remove('tabler:star');
        $uploader->remove(null);

        self::assertSame(['previous/icon.png'], $imageUploader->removed);
    }

    public function test_it_rejects_a_svg_whatever_its_name(): void
    {
        $imageUploader = $this->createImageUploader();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        self::assertNull((new TaxonIconUploader($imageUploader))->upload($this->createUploadedFile('pictogram.png', $svg)));
        self::assertSame([], $imageUploader->stored);
    }

    public function test_it_rejects_an_oversized_pictogram(): void
    {
        $imageUploader = $this->createImageUploader();
        $content = self::PNG_CONTENT . str_repeat("\0", 2 * 1024 * 1024);

        self::assertNull((new TaxonIconUploader($imageUploader))->upload($this->createUploadedFile('pictogram.png', $content)));
        self::assertSame([], $imageUploader->stored);
    }

    public function test_it_extracts_the_storage_path_of_an_uploaded_pictogram_only(): void
    {
        self::assertSame('ab/cd/icon.png', TaxonIconUploader::storagePath('image:ab/cd/icon.png'));
        self::assertNull(TaxonIconUploader::storagePath('tabler:star'));
        self::assertNull(TaxonIconUploader::storagePath(null));
    }

    private function createUploadedFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'at_icon_');
        self::assertIsString($path);
        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * @return ImageUploaderInterface&object{stored: list<string>, removed: list<string>}
     */
    private function createImageUploader(): ImageUploaderInterface
    {
        return new class() implements ImageUploaderInterface {
            /** @var list<string> */
            public array $stored = [];

            /** @var list<string> */
            public array $removed = [];

            public function upload(ImageInterface $image): void
            {
                $path = sprintf('uploaded/%d.png', count($this->stored) + 1);
                $image->setPath($path);
                $this->stored[] = $path;
            }

            public function remove(string $path): bool
            {
                $this->removed[] = $path;

                return true;
            }
        };
    }
}
