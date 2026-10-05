<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Service;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\UploadedImageResizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadedImageResizerTest extends TestCase
{
    public function test_it_resizes_large_uploaded_images(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD extension is required for image resizing tests.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'at_image_');
        if ($tempPath === false) {
            self::fail('Unable to create a temporary image file.');
        }

        $image = imagecreatetruecolor(3000, 2000);
        self::assertNotFalse($image);
        $white = imagecolorallocate($image, 255, 255, 255);
        self::assertNotFalse($white);
        imagefill($image, 0, 0, $white);
        imagejpeg($image, $tempPath, 90);
        imagedestroy($image);

        $uploadedFile = new UploadedFile($tempPath, 'large-photo.jpg', 'image/jpeg', null, true);

        $resizer = new UploadedImageResizer(1600, 2 * 1024 * 1024, 82);
        $resizer->resizeIfNeeded($uploadedFile);

        $imageSize = getimagesize($uploadedFile->getPathname());
        self::assertIsArray($imageSize);
        [$width, $height] = $imageSize;

        self::assertLessThanOrEqual(1600, $width);
        self::assertLessThanOrEqual(1600, $height);

        @unlink($tempPath);
    }

    public function test_it_keeps_small_images_untouched(): void
    {
        if (!extension_loaded('gd')) {
            self::markTestSkipped('GD extension is required for image resizing tests.');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'at_image_');
        if ($tempPath === false) {
            self::fail('Unable to create a temporary image file.');
        }

        $image = imagecreatetruecolor(200, 100);
        self::assertNotFalse($image);
        imagejpeg($image, $tempPath, 90);
        imagedestroy($image);

        $uploadedFile = new UploadedFile($tempPath, 'small-photo.jpg', 'image/jpeg', null, true);
        $before = filesize($tempPath);

        $resizer = new UploadedImageResizer(1600, 2 * 1024 * 1024, 82);
        $resizer->resizeIfNeeded($uploadedFile);

        self::assertSame($before, filesize($uploadedFile->getPathname()));

        @unlink($tempPath);
    }

    public function test_it_ignores_files_that_are_not_uploaded_files(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'at_image_');
        if ($tempPath === false) {
            self::fail('Unable to create a temporary file.');
        }

        file_put_contents($tempPath, 'not-an-image');

        $resizer = new UploadedImageResizer();
        $resizer->resizeIfNeeded(new File($tempPath));

        self::assertSame('not-an-image', file_get_contents($tempPath));

        @unlink($tempPath);
    }
}
