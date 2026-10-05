<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Behat\Hook\AfterScenario;
use Behat\Step\Then;
use Behat\Step\When;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\MegaMenuChannelInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonIconUploader;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Filesystem\Adapter\FilesystemAdapterInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Admin\Channel\MegaMenuFormElement;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Admin\Common\FormErrorsElement;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Admin\Taxon\AdvancedTaxonFormElement;
use Webmozart\Assert\Assert;

/**
 * Fills and checks the plugin fields of the admin taxon and channel forms. Opening and saving a form
 * goes through the Sylius steps ("I want to modify the ... taxon", "I save my changes").
 */
final class ManagingAdvancedTaxonContext implements Context
{
    /**
     * Binary content of a 1x1 PNG, used as the uploaded pictogram.
     */
    private const PICTOGRAM_CONTENT = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** @var list<string> files written to the local disk */
    private array $localFiles = [];

    /** @var list<string> files written to the image storage */
    private array $storedFiles = [];

    public function __construct(
        private readonly AdvancedTaxonFormElement $taxonForm,
        private readonly MegaMenuFormElement $megaMenuForm,
        private readonly FormErrorsElement $formErrors,
        private readonly EntityManagerInterface $entityManager,
        private readonly FilesystemAdapterInterface $imageStorage,
    ) {
    }

    #[AfterScenario]
    public function removeTheWrittenFiles(): void
    {
        foreach ($this->localFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        // The pictograms uploaded through the form, even when a later step failed.
        /** @var list<string> $icons */
        $icons = $this->entityManager->createQueryBuilder()
            ->select('taxon.icon')
            ->from(TaxonInterface::class, 'taxon')
            ->andWhere('taxon.icon LIKE :uploaded')
            ->setParameter('uploaded', AdvancedTaxonInterface::ICON_IMAGE_PREFIX . '%')
            ->getQuery()
            ->getSingleColumnResult();

        foreach ([...$this->storedFiles, ...array_map(TaxonIconUploader::storagePath(...), $icons)] as $path) {
            if ($path !== null && $this->imageStorage->has($path)) {
                $this->imageStorage->delete($path);
            }
        }

        $this->localFiles = [];
        $this->storedFiles = [];
    }

    /**
     * The plugin renders its own translations section, so the Sylius taxon form element cannot fill
     * the localized name and slug.
     */
    #[When('I set the taxon :field to :value in :localeCode')]
    public function iSetTheTaxonFieldTo(string $field, string $value, string $localeCode): void
    {
        $this->taxonForm->setTranslatedField($field, $localeCode, $value);
    }

    #[When('I set its color to :color')]
    public function iSetItsColorTo(string $color): void
    {
        $this->taxonForm->setColor($color);
    }

    #[When('I choose the :icon icon')]
    public function iChooseTheIcon(string $icon): void
    {
        $this->taxonForm->chooseIcon($icon);
    }

    #[When('I upload a pictogram')]
    public function iUploadAPictogram(): void
    {
        // The browser needs a real file to upload.
        $path = sprintf('%s/%s.png', sys_get_temp_dir(), uniqid('pictogram_', true));
        Assert::notFalse(file_put_contents($path, (string) base64_decode(self::PICTOGRAM_CONTENT, true)));
        $this->localFiles[] = $path;

        $this->taxonForm->uploadPictogram($path);
    }

    #[When('I pick the :icon icon in the icon picker')]
    public function iPickTheIconInTheIconPicker(string $icon): void
    {
        $this->taxonForm->pickIconInPicker($icon);
    }

    #[When('I display its featured products after the filters as a slider')]
    public function iDisplayItsFeaturedProductsAfterTheFiltersAsASlider(): void
    {
        $this->taxonForm->enableFeaturedProductsAsSlider(AdvancedTaxonInterface::FEATURED_PRODUCTS_POSITION_AFTER_FILTERS);
    }

    #[When('I make it a universe titled :title in :localeCode')]
    public function iMakeItAUniverseTitled(string $title, string $localeCode): void
    {
        $this->taxonForm->makeUniverse($localeCode, $title);
    }

    #[When('I add a media to the :zone zone')]
    public function iAddAMediaToTheZone(string $zone): void
    {
        $this->taxonForm->addMedia($zone);
    }

    #[When('I remove the first media of the :zone zone')]
    public function iRemoveTheFirstMediaOfTheZone(string $zone): void
    {
        $this->taxonForm->removeFirstMedia($zone);
    }

    #[When('I add a condition on the product name containing :value')]
    public function iAddAConditionOnTheProductNameContaining(string $value): void
    {
        $this->taxonForm->addNameCondition($value);
    }

    #[When('I add a condition on an attribute without choosing the attribute')]
    public function iAddAConditionOnAnAttributeWithoutChoosingTheAttribute(): void
    {
        $this->taxonForm->addAttributeConditionWithoutAttribute('steel');
    }

    #[When('I test the conditions')]
    public function iTestTheConditions(): void
    {
        $this->taxonForm->testConditions();
    }

    #[When('I enable its mega menu')]
    public function iEnableItsMegaMenu(): void
    {
        $this->megaMenuForm->enableMegaMenu();
    }

    #[Then('/^the ("[^"]+" taxon) should have the "([^"]+)" color$/')]
    public function theTaxonShouldHaveTheColor(TaxonInterface $taxon, string $color): void
    {
        Assert::same($this->reload($taxon)->getColor(), $color);
    }

    #[Then('/^the ("[^"]+" taxon) should use the "([^"]+)" icon$/')]
    public function theTaxonShouldUseTheIcon(TaxonInterface $taxon, string $icon): void
    {
        $taxon = $this->reload($taxon);

        Assert::same($taxon->getIcon(), $icon);
        Assert::same($taxon->getIconType(), 'icon');
    }

    #[Then('/^the pictogram of the ("[^"]+" taxon) should be stored in the Sylius image storage$/')]
    public function thePictogramOfTheTaxonShouldBeStoredInTheSyliusImageStorage(TaxonInterface $taxon): void
    {
        $taxon = $this->reload($taxon);
        $storagePath = TaxonIconUploader::storagePath($taxon->getIcon());

        Assert::notNull($storagePath, sprintf('The taxon has no uploaded pictogram: "%s".', (string) $taxon->getIcon()));
        $this->storedFiles[] = $storagePath;
        Assert::same($taxon->getIconType(), 'image');
        Assert::true($this->imageStorage->has($storagePath), sprintf('The pictogram file "%s" is missing from the image storage.', $storagePath));
    }

    #[Then('the icon field should hold :icon')]
    public function theIconFieldShouldHold(string $icon): void
    {
        Assert::same($this->taxonForm->getIcon(), $icon);
    }

    #[Then('/^the ("[^"]+" taxon) should display its featured products after the filters as a slider$/')]
    public function theTaxonShouldDisplayItsFeaturedProductsAfterTheFiltersAsASlider(TaxonInterface $taxon): void
    {
        $taxon = $this->reload($taxon);

        Assert::true($taxon->isFeaturedProductsActive());
        Assert::same($taxon->getFeaturedProductsPosition(), AdvancedTaxonInterface::FEATURED_PRODUCTS_POSITION_AFTER_FILTERS);
        Assert::true($taxon->isSlider());
    }

    #[Then('/^the ("[^"]+" taxon) should be a universe titled "([^"]+)"$/')]
    public function theTaxonShouldBeAUniverseTitled(TaxonInterface $taxon, string $title): void
    {
        $taxon = $this->reload($taxon);

        Assert::true($taxon->isUniverse());
        Assert::same($taxon->getUniversePageTitle('en_US'), $title);
    }

    #[Then('/^the "([^"]+)" zone should (not )?offer the card text option$/')]
    public function theZoneShouldOfferTheCardTextOption(string $zone, string $not = ''): void
    {
        $this->assertZoneOffers($zone, '[showCardText]', $not === '');
    }

    #[Then('/^the "([^"]+)" zone should (not )?offer a destination URL$/')]
    public function theZoneShouldOfferADestinationUrl(string $zone, string $not = ''): void
    {
        $this->assertZoneOffers($zone, '[url]', $not === '');
    }

    #[Then('the next position offered by the :zone zone should be :position')]
    public function theNextPositionOfferedByTheZoneShouldBe(string $zone, int $position): void
    {
        Assert::same(
            1,
            preg_match('/<input[^>]*\[position\][^>]*\svalue="(?<position>\d+)"/', $this->taxonForm->getZonePrototype($zone), $matches),
            sprintf('The "%s" zone does not pre-fill any media position.', $zone),
        );
        Assert::same($matches['position'] ?? '', (string) $position, sprintf('Unexpected pre-filled position for the "%s" zone.', $zone));
    }

    #[Then('the :zone zone media positions should be :positions')]
    public function theZoneMediaPositionsShouldBe(string $zone, string $positions): void
    {
        Assert::same($this->taxonForm->getZoneMediaPositions($zone), explode(',', $positions));
    }

    /**
     * A media added in the browser must never reuse the field names of a media already listed,
     * which would make one of them overwrite the other on submit.
     */
    #[Then('the :zone zone media should have distinct field names')]
    public function theZoneMediaShouldHaveDistinctFieldNames(string $zone): void
    {
        $names = $this->taxonForm->getZoneMediaFieldNames($zone);

        Assert::notEmpty($names, sprintf('The "%s" zone does not hold any media.', $zone));
        Assert::same(array_unique($names), $names, sprintf('The "%s" zone media share field names.', $zone));
    }

    #[Then('the conditions preview should list :count matching product(s)')]
    public function theConditionsPreviewShouldListMatchingProducts(int $count): void
    {
        $this->taxonForm->waitForPreviewSummary(sprintf('%d product(s) match the criteria.', $count));

        Assert::same($this->taxonForm->countPreviewedProducts(), $count);
    }

    #[Then('I should be told to choose the attribute of the condition')]
    public function iShouldBeToldToChooseTheAttributeOfTheCondition(): void
    {
        Assert::contains($this->taxonForm->getConditionErrors(), 'Select the attribute, option or taxon this condition applies to.');
    }

    #[Then('the conditions tab should be flagged as holding an error')]
    public function theConditionsTabShouldBeFlaggedAsHoldingAnError(): void
    {
        Assert::same($this->taxonForm->getTabsInError(), ['#taxon-tab-virtual-conditions']);
    }

    #[Then('/^I should be told that the ("[^"]+" taxon) cannot be the main taxon of a product$/')]
    public function iShouldBeToldThatTheTaxonCannotBeTheMainTaxonOfAProduct(TaxonInterface $taxon): void
    {
        $message = sprintf('The universe "%s" cannot be the main taxon of a product', (string) $taxon->getName());

        Assert::true($this->formErrors->hasError($message), sprintf('The error "%s" is not displayed.', $message));
    }

    #[Then('/^(this channel) should have the mega menu enabled$/')]
    public function thisChannelShouldHaveTheMegaMenuEnabled(ChannelInterface $channel): void
    {
        $this->entityManager->refresh($channel);

        Assert::isInstanceOf($channel, MegaMenuChannelInterface::class);
        Assert::true($channel->hasMegaMenu());
    }

    private function assertZoneOffers(string $zone, string $field, bool $expected): void
    {
        Assert::same(
            str_contains($this->taxonForm->getZonePrototype($zone), $field),
            $expected,
            sprintf('The "%s" zone %s offer the "%s" field.', $zone, $expected ? 'must' : 'must not', $field),
        );
    }

    /**
     * The browser saves the form in another process: the entity in memory is stale.
     */
    private function reload(TaxonInterface $taxon): AdvancedTaxonInterface
    {
        $this->entityManager->refresh($taxon);
        Assert::isInstanceOf($taxon, AdvancedTaxonInterface::class);

        return $taxon;
    }
}
