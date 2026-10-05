<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Integration;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\EventListener\TaxonIconRemovalListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Sylius\Component\Core\Filesystem\Adapter\FilesystemAdapterInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webmozart\Assert\Assert;

/**
 * Deleting a taxon removes the pictogram it uploaded, never a file it only names.
 */
final class TaxonIconRemovalListenerTest extends KernelTestCase
{
    private const PNG_CONTENT = "\x89PNG\r\n\x1a\n";

    private EntityManagerInterface $entityManager;

    private FilesystemAdapterInterface $storage;

    private CatalogBuilder $catalog;

    /** @var list<string> */
    private array $storedFiles = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);
        $this->entityManager = $entityManager;
        $this->entityManager->beginTransaction();
        $this->catalog = new CatalogBuilder(self::getContainer(), $this->entityManager);

        $storage = self::getContainer()->get(FilesystemAdapterInterface::class);
        Assert::isInstanceOf($storage, FilesystemAdapterInterface::class);
        $this->storage = $storage;
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();

        foreach ($this->storedFiles as $path) {
            if ($this->storage->has($path)) {
                $this->storage->delete($path);
            }
        }

        parent::tearDown();
    }

    public function test_deleting_a_taxon_removes_its_uploaded_pictogram(): void
    {
        $path = $this->store();
        $taxon = $this->taxonWithIcon('image:' . $path);

        $this->entityManager->remove($taxon);
        $this->entityManager->flush();

        self::assertFalse($this->storage->has($path));
    }

    public function test_replacing_an_uploaded_pictogram_removes_the_file_once_saved(): void
    {
        $path = $this->store();
        $taxon = $this->taxonWithIcon('image:' . $path);

        $taxon->setIcon('tabler:folder');
        self::assertTrue($this->storage->has($path));

        $this->entityManager->flush();

        self::assertFalse($this->storage->has($path));
    }

    public function test_deleting_a_taxon_with_a_glyph_leaves_the_storage_alone(): void
    {
        // A glyph name that happens to match a stored file.
        $path = $this->store();
        $taxon = $this->taxonWithIcon($path);

        $this->entityManager->remove($taxon);
        $this->entityManager->flush();

        self::assertTrue($this->storage->has($path));
    }

    /**
     * A failed flush closes its entity manager: the file it scheduled stays, even once the manager
     * that replaces it flushes.
     */
    public function test_a_file_scheduled_by_a_failed_flush_is_left_in_place(): void
    {
        $path = $this->store();
        $taxon = $this->taxonWithIcon('image:' . $path);

        $listener = self::getContainer()->get('cyllene_digital_sylius_advanced_taxon.listener.taxon_icon_removal');
        Assert::isInstanceOf($listener, TaxonIconRemovalListener::class);
        $failedManager = $this->createStub(EntityManagerInterface::class);
        $listener->postRemove(new PostRemoveEventArgs($taxon, $failedManager));

        $listener->postFlush(new PostFlushEventArgs($this->entityManager));

        self::assertTrue($this->storage->has($path));
    }

    private function taxonWithIcon(string $icon): AdvancedTaxonInterface
    {
        $taxon = $this->catalog->taxon(uniqid('Pictogram ', false));
        $taxon->setIcon($icon);
        $this->entityManager->flush();

        return $taxon;
    }

    private function store(): string
    {
        $path = sprintf('at/%s.png', uniqid('', false));
        $this->storage->write($path, self::PNG_CONTENT);
        $this->storedFiles[] = $path;

        return $path;
    }
}
