<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Integration;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonImageInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\AdminBundle\Form\Type\TaxonType;
use Sylius\Component\Core\Filesystem\Adapter\FilesystemAdapterInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Webmozart\Assert\Assert;

final class TaxonFormTest extends KernelTestCase
{
    private const PNG_CONTENT = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\x9cc\xf8\x0f\x00\x00\x01\x01\x00\x05\x18\xd8N\x00\x00\x00\x00IEND\xaeB`\x82";

    /** @var list<string> */
    private array $temporaryFiles = [];

    /** @var list<string> */
    private array $storedFiles = [];

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);
        $this->entityManager = $entityManager;
        $this->entityManager->beginTransaction();

        // The taxon form is built with the locales defined in the database.
        (new CatalogBuilder(self::getContainer(), $this->entityManager))->locale();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();

        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $storage = $this->storage();
        foreach ($this->storedFiles as $path) {
            if ($storage->has($path)) {
                $storage->delete($path);
            }
        }

        parent::tearDown();
    }

    public function test_a_new_media_goes_through_the_sylius_image_constraints(): void
    {
        $form = $this->submit([['file' => $this->uploadedFile('evil.html', '<html><script>alert(1)</script></html>'), 'position' => '1']]);

        self::assertFalse($form->isValid());
        $constraints = [];
        foreach ($form->get('mediaTop')->getErrors(true, true) as $error) {
            $cause = $error instanceof FormError ? $error->getCause() : null;
            if ($cause instanceof ConstraintViolationInterface && $cause->getConstraint() !== null) {
                $constraints[] = $cause->getConstraint()::class;
            }
        }
        self::assertContains(Image::class, $constraints);
    }

    public function test_a_media_without_position_is_placed_first(): void
    {
        $taxon = $this->createTaxon();
        $form = $this->submit([['file' => $this->uploadedFile('banner.png', self::PNG_CONTENT), 'position' => '']], $taxon);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        $media = $taxon->getImages()->first();
        self::assertInstanceOf(AdvancedTaxonImageInterface::class, $media);
        self::assertSame(0, $media->getPosition());
    }

    public function test_the_color_input_alone_never_gives_a_color(): void
    {
        // Browsers always submit a color input, #000000 when nothing was picked.
        $withoutColor = $this->createTaxon();
        $this->submit([], $withoutColor, ['color' => '#000000']);

        $withColor = $this->createTaxon();
        $this->submit([], $withColor, ['hasColor' => '1', 'color' => '#336699']);

        self::assertNull($withoutColor->getColor());
        self::assertSame('#336699', $withColor->getColor());
    }

    public function test_an_uploaded_pictogram_is_never_set_through_the_icon_text_field(): void
    {
        $taxon = $this->createTaxon();
        $taxon->setIcon('tabler:folder');

        $this->submit([], $taxon, ['icon' => 'image:someone/else.png']);

        self::assertSame('tabler:folder', $taxon->getIcon());
    }

    public function test_replacing_an_uploaded_pictogram_by_a_glyph_removes_the_file(): void
    {
        $storage = $this->storage();
        $path = $this->storePictogram();

        $taxon = $this->savedTaxonWithIcon('image:' . $path);

        $form = $this->submit([], $taxon, ['icon' => 'tabler:folder']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame('tabler:folder', $taxon->getIcon());
        self::assertTrue($storage->has($path), 'Kept until the change is saved.');
        $this->entityManager->flush();
        self::assertFalse($storage->has($path));
    }

    public function test_saving_a_taxon_keeps_its_native_main_image_in_the_main_image_zone(): void
    {
        $taxon = $this->createTaxon();
        $image = $this->media('', 'legacy/main.png');
        $image->setType(null);
        $taxon->addImage($image);

        $form = $this->submit([], $taxon);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertTrue($taxon->getImages()->contains($image));
        self::assertSame('main', $image->getType());
    }

    public function test_an_invalid_pictogram_file_is_refused_and_the_previous_one_kept(): void
    {
        $previous = $this->storePictogram();
        $taxon = $this->createTaxon();
        $taxon->setIcon('image:' . $previous);

        $form = $this->submit([], $taxon, ['iconType' => 'image', 'iconFile' => $this->uploadedFile('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')]);

        self::assertCount(1, $form->get('iconFile')->getErrors());
        self::assertSame('image:' . $previous, $taxon->getIcon());
        self::assertTrue($this->storage()->has($previous));
    }

    public function test_a_new_pictogram_replaces_the_previous_file(): void
    {
        $previous = $this->storePictogram();
        $taxon = $this->savedTaxonWithIcon('image:' . $previous);

        $form = $this->submit([], $taxon, ['iconType' => 'image', 'iconFile' => $this->uploadedFile('logo.png', self::PNG_CONTENT)]);
        $this->entityManager->flush();

        $icon = (string) $taxon->getIcon();
        $this->storedFiles[] = substr($icon, 6);
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertStringStartsWith('image:', $icon);
        self::assertNotSame('image:' . $previous, $icon);
        self::assertTrue($this->storage()->has(substr($icon, 6)));
        self::assertFalse($this->storage()->has($previous));
    }

    public function test_an_icon_the_shop_cannot_render_is_refused(): void
    {
        $taxon = $this->createTaxon();

        $form = $this->submit([], $taxon, ['icon' => 'watch']);

        $errors = iterator_to_array($form->get('icon')->getErrors(), false);
        self::assertCount(1, $errors);
        self::assertInstanceOf(FormError::class, $errors[0]);
        self::assertStringContainsString('"watch"', $errors[0]->getMessage());
    }

    public function test_an_icon_of_a_local_set_is_accepted_with_or_without_prefix(): void
    {
        foreach (['folder', 'tabler:folder', 'at:photo'] as $icon) {
            $taxon = $this->createTaxon();
            $form = $this->submit([], $taxon, ['icon' => $icon]);

            self::assertCount(0, $form->get('icon')->getErrors(), $icon);
            self::assertSame($icon, $taxon->getIcon());
        }
    }

    public function test_a_text_longer_than_its_column_is_a_form_error(): void
    {
        $form = $this->submit([], null, ['featuredItemsTranslations' => ['en_US' => ['featuredProductsTitle' => str_repeat('a', 256)]]]);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('featuredItemsTranslations')->get('en_US')->get('featuredProductsTitle')->getErrors());
    }

    public function test_removing_the_media_of_a_zone_keeps_the_other_zones(): void
    {
        $taxon = $this->createTaxon();
        $top = $this->media('top', 'top/banner.png');
        $bottom = $this->media('bottom', 'bottom/banner.png');
        $taxon->addImage($top);
        $taxon->addImage($bottom);
        // Saved media are matched by id.
        $taxon->setName('Caps');
        $taxon->setSlug((string) $taxon->getCode());
        $this->entityManager->persist($taxon);
        $this->entityManager->flush();

        $form = $this->submit([], $taxon);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertFalse($taxon->getImages()->contains($top));
        self::assertTrue($taxon->getImages()->contains($bottom));
    }

    /**
     * @param list<array<string, mixed>> $topMedia
     * @param array<string, mixed> $extra
     */
    private function submit(array $topMedia, ?AdvancedTaxonInterface $taxon = null, array $extra = []): FormInterface
    {
        $taxon ??= $this->createTaxon();

        $formFactory = self::getContainer()->get('form.factory');
        Assert::isInstanceOf($formFactory, FormFactoryInterface::class);

        $form = $formFactory->create(TaxonType::class, $taxon, ['csrf_protection' => false]);
        $form->submit([
            'code' => $taxon->getCode(),
            'translations' => ['en_US' => ['name' => 'Caps', 'slug' => (string) $taxon->getCode()]],
            'iconType' => 'icon',
            'featuredProductsPosition' => 'before_filters',
            'mediaDisplayModeTop' => 'stacked',
            'mediaDisplayModeBottom' => 'stacked',
            'mediaDisplayModeLeft' => 'stacked',
            'mediaDisplayModeProduct' => 'products_end',
            'mediaDisplayModeFeatured' => 'stacked',
            'mediaTop' => $topMedia,
            ...$extra,
        ], false);

        return $form;
    }

    private function storage(): FilesystemAdapterInterface
    {
        $storage = self::getContainer()->get(FilesystemAdapterInterface::class);
        Assert::isInstanceOf($storage, FilesystemAdapterInterface::class);

        return $storage;
    }

    private function savedTaxonWithIcon(string $icon): AdvancedTaxonInterface
    {
        $taxon = $this->createTaxon();
        $taxon->setName('Caps');
        $taxon->setSlug((string) $taxon->getCode());
        $taxon->setIcon($icon);
        $this->entityManager->persist($taxon);
        $this->entityManager->flush();

        return $taxon;
    }

    private function storePictogram(): string
    {
        $path = sprintf('at/%s.png', uniqid('', false));
        $this->storage()->write($path, self::PNG_CONTENT);
        $this->storedFiles[] = $path;

        return $path;
    }

    private function media(string $type, string $path): AdvancedTaxonImageInterface
    {
        $imageFactory = self::getContainer()->get('sylius.factory.taxon_image');
        Assert::isInstanceOf($imageFactory, FactoryInterface::class);
        $image = $imageFactory->createNew();
        Assert::isInstanceOf($image, AdvancedTaxonImageInterface::class);
        $image->setType($type);
        $image->setPath($path);

        return $image;
    }

    private function createTaxon(): AdvancedTaxonInterface
    {
        $factory = self::getContainer()->get('sylius.factory.taxon');
        Assert::isInstanceOf($factory, FactoryInterface::class);

        $taxon = $factory->createNew();
        Assert::isInstanceOf($taxon, AdvancedTaxonInterface::class);
        $taxon->setCode(uniqid('at_form_', false));
        $taxon->setCurrentLocale('en_US');
        $taxon->setFallbackLocale('en_US');

        return $taxon;
    }

    private function uploadedFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'at_media_');
        Assert::string($path);
        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;

        return new UploadedFile($path, $name, null, null, true);
    }
}
