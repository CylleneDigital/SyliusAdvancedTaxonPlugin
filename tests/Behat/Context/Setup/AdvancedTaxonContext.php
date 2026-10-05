<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Context\Setup;

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonImageInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\MegaMenuChannelInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Filesystem\Adapter\FilesystemAdapterInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

/**
 * Puts taxons and channels in the state a scenario starts from, without going through the forms.
 */
final class AdvancedTaxonContext implements Context
{
    /**
     * Binary content of a 1x1 PNG, written to the image storage for every media.
     */
    private const IMAGE_CONTENT = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private const LOCALE = 'en_US';

    /** @var list<string> */
    private array $storedFiles = [];

    /**
     * @param FactoryInterface<AdvancedTaxonImageInterface> $taxonImageFactory
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FactoryInterface $taxonImageFactory,
        private readonly FilesystemAdapterInterface $imageStorage,
    ) {
    }

    #[AfterScenario]
    public function removeTheStoredMedia(): void
    {
        foreach ($this->storedFiles as $path) {
            if ($this->imageStorage->has($path)) {
                $this->imageStorage->delete($path);
            }
        }

        $this->storedFiles = [];
    }

    #[Given('/^the ("[^"]+" taxon) has the "(#[0-9a-fA-F]{6})" color$/')]
    public function theTaxonHasTheColor(TaxonInterface $taxon, string $color): void
    {
        $this->advanced($taxon)->setColor($color);
        $this->entityManager->flush();
    }

    #[Given('/^the ("[^"]+" taxon) uses the "([^"]+)" icon$/')]
    public function theTaxonUsesTheIcon(TaxonInterface $taxon, string $icon): void
    {
        $advancedTaxon = $this->advanced($taxon);
        $advancedTaxon->setIcon($icon);
        $advancedTaxon->setIconType('icon');
        $this->entityManager->flush();
    }

    #[Given('/^the ("[^"]+" taxon) hides its color and icon (in the menu|on its page)$/')]
    public function theTaxonHidesItsColorAndIcon(TaxonInterface $taxon, string $where): void
    {
        $advancedTaxon = $this->advanced($taxon);
        if ($where === 'in the menu') {
            $advancedTaxon->setShowCustomizationInMenu(false);
        } else {
            $advancedTaxon->setShowCustomizationOnTaxonPage(false);
        }

        $this->entityManager->flush();
    }

    #[Given('/^the ("[^"]+" taxon) has a media in the "([^"]+)" zone at position (\d+)$/')]
    public function theTaxonHasAMediaInTheZoneAtPosition(TaxonInterface $taxon, string $zone, int $position): void
    {
        $this->addMedia($taxon, $zone, $position, null);
    }

    #[Given('/^the ("[^"]+" taxon) has a media titled "([^"]+)" in the "([^"]+)" zone$/')]
    public function theTaxonHasAMediaTitledInTheZone(TaxonInterface $taxon, string $title, string $zone): void
    {
        $this->addMedia($taxon, $zone, 0, $title);
    }

    #[Given('/^the ("[^"]+" taxon) inserts its media cards (at the start|in the middle) of the product list$/')]
    public function theTaxonInsertsItsMediaCards(TaxonInterface $taxon, string $where): void
    {
        $this->advanced($taxon)->setMediaDisplayModeProduct($where === 'at the start'
            ? AdvancedTaxonInterface::MEDIA_RIGHT_PRODUCTS_POSITION_START
            : AdvancedTaxonInterface::MEDIA_RIGHT_PRODUCTS_POSITION_RANDOM_MIDDLE);
        $this->entityManager->flush();
    }

    #[Given('/^the ("[^"]+" taxon) shows its top zone as a slider$/')]
    public function theTaxonShowsItsTopZoneAsASlider(TaxonInterface $taxon): void
    {
        $this->advanced($taxon)->setMediaDisplayModeTop(AdvancedTaxonInterface::MEDIA_DISPLAY_MODE_SLIDER);
        $this->entityManager->flush();
    }

    /**
     * The main image as Sylius saves it, before the plugin gives it the "main" type.
     */
    #[Given('/^the ("[^"]+" taxon) has an image without type$/')]
    public function theTaxonHasAnImageWithoutType(TaxonInterface $taxon): void
    {
        $this->addMedia($taxon, null, 0, null);
    }

    #[Given('/^the ("[^"]+" taxon) features the ("[^"]+" taxon)$/')]
    public function theTaxonFeaturesTheTaxon(TaxonInterface $taxon, TaxonInterface $featuredChild): void
    {
        $this->advanced($taxon)->addFeaturedChild($this->advanced($featuredChild));
        $this->entityManager->flush();
    }

    #[Given('/^the ("[^"]+" taxon) features the ("[^"]+" product)$/')]
    public function theTaxonFeaturesTheProduct(TaxonInterface $taxon, ProductInterface $product): void
    {
        $this->advanced($taxon)->addFeaturedProduct($product);
        $this->entityManager->flush();
    }

    #[Given('/^the ("[^"]+" taxon) displays its featured products$/')]
    public function theTaxonDisplaysItsFeaturedProducts(TaxonInterface $taxon): void
    {
        $this->advanced($taxon)->setIsFeaturedProductsActive(true);
        $this->entityManager->flush();
    }

    #[Given('/^the ("[^"]+" taxon) has advanced filters$/')]
    public function theTaxonHasAdvancedFilters(TaxonInterface $taxon): void
    {
        $this->advanced($taxon)->setAdvancedFiltersEnabled(true);
        $this->entityManager->flush();
    }

    #[Given('/^the ("[^"]+" taxon) is a universe$/')]
    public function theTaxonIsAUniverse(TaxonInterface $taxon): void
    {
        $this->advanced($taxon)->setIsUniverse(true);
        $this->entityManager->flush();
    }

    #[Given('/^the ("[^"]+" taxon) has the universe page title "([^"]+)"$/')]
    public function theTaxonHasTheUniversePageTitle(TaxonInterface $taxon, string $title): void
    {
        $this->advanced($taxon)->getOrCreateFeaturedItemsTranslation(self::LOCALE)->setUniversePageTitle($title);
        $this->entityManager->flush();
    }

    #[Given('/^(this channel) has the mega menu enabled$/')]
    public function thisChannelHasTheMegaMenuEnabled(ChannelInterface $channel): void
    {
        Assert::isInstanceOf($channel, MegaMenuChannelInterface::class);
        $channel->setHasMegaMenu(true);
        $this->entityManager->flush();
    }

    private function addMedia(TaxonInterface $taxon, ?string $zone, int $position, ?string $title): void
    {
        $path = sprintf('at-behat/%s.png', uniqid('', true));
        $this->imageStorage->write($path, (string) base64_decode(self::IMAGE_CONTENT, true));
        $this->storedFiles[] = $path;

        $media = $this->taxonImageFactory->createNew();
        $media->setCurrentLocale(self::LOCALE);
        $media->setFallbackLocale(self::LOCALE);
        $media->setType($zone);
        $media->setPosition($position);
        $media->setPath($path);
        if ($title !== null) {
            $media->setTitle($title);
        }

        $taxon->addImage($media);
        $this->entityManager->flush();
    }

    private function advanced(TaxonInterface $taxon): AdvancedTaxonInterface
    {
        Assert::isInstanceOf($taxon, AdvancedTaxonInterface::class);

        return $taxon;
    }
}
