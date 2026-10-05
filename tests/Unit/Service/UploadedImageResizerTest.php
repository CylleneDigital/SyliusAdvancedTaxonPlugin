<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\UploadedImageResizer;
use GdImage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[RequiresPhpExtension('gd')]
final class UploadedImageResizerTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * @return iterable<string, array{'image/jpeg'|'image/png'|'image/webp'|'image/gif'}>
     */
    public static function formats(): iterable
    {
        yield 'jpeg' => ['image/jpeg'];
        yield 'png' => ['image/png'];
        yield 'webp' => ['image/webp'];
        yield 'gif' => ['image/gif'];
    }

    /**
     * @param 'image/jpeg'|'image/png'|'image/webp'|'image/gif' $mimeType
     */
    #[DataProvider('formats')]
    public function test_it_downscales_a_large_image_to_the_maximum_dimension_in_its_format(string $mimeType): void
    {
        $path = $this->createImage(2400, 1200, $mimeType);

        (new UploadedImageResizer())->resizeIfNeeded($this->upload($path, $mimeType));

        self::assertSame([1600, 800, $mimeType], $this->describe($path));
    }

    public function test_it_keeps_the_transparency_of_a_png(): void
    {
        $path = $this->createImage(2400, 1200, 'image/png', transparent: true);

        (new UploadedImageResizer())->resizeIfNeeded($this->upload($path, 'image/png'));

        $resized = imagecreatefrompng($path);
        self::assertInstanceOf(GdImage::class, $resized);
        self::assertSame(127, (imagecolorat($resized, 10, 10) >> 24) & 0x7F);
    }

    public function test_it_reencodes_a_heavy_file_whose_dimensions_are_small_enough(): void
    {
        $path = $this->createImage(200, 100, 'image/jpeg', quality: 100);
        $before = md5_file($path);

        (new UploadedImageResizer(1600, 1, 50))->resizeIfNeeded($this->upload($path, 'image/jpeg'));

        self::assertSame([200, 100, 'image/jpeg'], $this->describe($path));
        self::assertNotSame($before, md5_file($path));
    }

    public function test_it_keeps_small_images_untouched(): void
    {
        $path = $this->createImage(200, 100, 'image/jpeg');
        $before = md5_file($path);

        (new UploadedImageResizer())->resizeIfNeeded($this->upload($path, 'image/jpeg'));

        self::assertSame($before, md5_file($path));
    }

    /**
     * In its own process: the memory the rest of the suite holds would blur the limit set here.
     */
    #[RunInSeparateProcess]
    public function test_it_keeps_an_image_it_cannot_decode_within_the_memory_limit(): void
    {
        $path = $this->createImage(3000, 2000, 'image/jpeg');
        $before = md5_file($path);

        $memoryLimit = (string) ini_get('memory_limit');
        self::assertNotFalse(ini_set('memory_limit', (string) (memory_get_usage(true) + 8 * 1024 * 1024)));

        try {
            (new UploadedImageResizer())->resizeIfNeeded($this->upload($path, 'image/jpeg'));
        } finally {
            ini_set('memory_limit', $memoryLimit);
        }

        self::assertSame($before, md5_file($path));
    }

    public function test_it_keeps_a_format_it_does_not_handle(): void
    {
        $path = $this->createImage(2400, 1200, 'image/bmp');
        $before = md5_file($path);

        (new UploadedImageResizer())->resizeIfNeeded($this->upload($path, 'image/bmp'));

        self::assertSame($before, md5_file($path));
    }

    public function test_it_ignores_an_uploaded_file_that_is_not_an_image(): void
    {
        $path = $this->temporaryFile();
        file_put_contents($path, str_repeat('not-an-image', 1000));

        (new UploadedImageResizer(1600, 1))->resizeIfNeeded($this->upload($path, 'text/plain'));

        self::assertSame(str_repeat('not-an-image', 1000), file_get_contents($path));
    }

    public function test_it_ignores_files_that_are_not_uploaded_files(): void
    {
        $path = $this->createImage(2400, 1200, 'image/jpeg');
        $before = md5_file($path);

        (new UploadedImageResizer())->resizeIfNeeded(new File($path));

        self::assertSame($before, md5_file($path));
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     * @param 'image/jpeg'|'image/png'|'image/webp'|'image/gif'|'image/bmp' $mimeType
     */
    private function createImage(int $width, int $height, string $mimeType, bool $transparent = false, int $quality = 90): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertInstanceOf(GdImage::class, $image);

        if ($transparent) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $color = imagecolorallocatealpha($image, 0, 0, 0, 127);
        } else {
            $color = imagecolorallocate($image, 200, 120, 40);
        }
        self::assertNotFalse($color);
        imagefill($image, 0, 0, $color);

        $path = $this->temporaryFile();
        $written = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $path, $quality),
            'image/png' => imagepng($image, $path),
            'image/webp' => imagewebp($image, $path),
            'image/gif' => imagegif($image, $path),
            'image/bmp' => imagebmp($image, $path),
        };
        self::assertTrue($written);

        return $path;
    }

    private function upload(string $path, string $mimeType): UploadedFile
    {
        return new UploadedFile($path, basename($path), $mimeType, null, true);
    }

    /**
     * @return array{int, int, string}
     */
    private function describe(string $path): array
    {
        $size = getimagesize($path);
        self::assertIsArray($size);

        return [$size[0], $size[1], $size['mime']];
    }

    private function temporaryFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'at_resizer_');
        self::assertIsString($path);
        $this->files[] = $path;

        return $path;
    }
}
