<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Context\Ui\Admin;

use Behat\Mink\Element\NodeElement;
use Behat\MinkExtension\Context\RawMinkContext;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonImage;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonIconUploader;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Webmozart\Assert\Assert;

/**
 * Fills and asserts the plugin specific fields of the admin taxon form.
 */
final class ManagingAdvancedTaxonContext extends RawMinkContext
{
    /**
     * Name of the admin taxon form, used to build the field names.
     */
    private const FORM_NAME = 'sylius_admin_taxon';

    /**
     * Binary content of a 1x1 PNG, used as the uploaded pictogram.
     */
    private const PICTOGRAM_CONTENT = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /**
     * @param TaxonRepositoryInterface<Taxon> $taxonRepository
     */
    public function __construct(
        private readonly TaxonRepositoryInterface $taxonRepository,
        private readonly TaxonIconUploader $taxonIconUploader,
        private readonly string $projectDir,
    ) {
    }

    /**
     * @When I set its color to :color
     */
    public function iSetItsColorTo(string $color): void
    {
        $this->getSession()->getPage()->fillField(self::FORM_NAME . '[color]', $color);
    }

    /**
     * @When I choose the :icon icon
     */
    public function iChooseTheIcon(string $icon): void
    {
        $page = $this->getSession()->getPage();
        $page->selectFieldOption(self::FORM_NAME . '[iconType]', 'icon');
        $page->fillField(self::FORM_NAME . '[icon]', $icon);
    }

    /**
     * The plugin renders its own translations section, so the native Sylius taxon element cannot be
     * used to fill the localized name and slug.
     *
     * @When I set the taxon name to :name in :localeCode
     */
    public function iSetTheTaxonNameTo(string $name, string $localeCode): void
    {
        $this->getSession()->getPage()->fillField(sprintf('%s[translations][%s][name]', self::FORM_NAME, $localeCode), $name);
    }

    /**
     * @When I set the taxon slug to :slug in :localeCode
     */
    public function iSetTheTaxonSlugTo(string $slug, string $localeCode): void
    {
        $this->getSession()->getPage()->fillField(sprintf('%s[translations][%s][slug]', self::FORM_NAME, $localeCode), $slug);
    }

    /**
     * @When I upload the :filename pictogram
     */
    public function iUploadThePictogram(string $filename): void
    {
        $page = $this->getSession()->getPage();
        $page->selectFieldOption(self::FORM_NAME . '[iconType]', 'image');

        $input = $page->find('css', sprintf('input[name="%s[iconFile]"]', self::FORM_NAME));
        Assert::notNull($input, 'The pictogram file input could not be found in the taxon form.');

        $input->attachFile($this->createPictogramFile($filename));
    }

    /**
     * @Then the taxon with code :code should have the :color color
     */
    public function theTaxonWithCodeShouldHaveTheColor(string $code, string $color): void
    {
        Assert::same($this->getTaxon($code)->getColor(), $color);
    }

    /**
     * @Then the taxon with code :code should use the :icon icon
     */
    public function theTaxonWithCodeShouldUseTheIcon(string $code, string $icon): void
    {
        $taxon = $this->getTaxon($code);

        Assert::same($taxon->getIcon(), $icon);
        Assert::same($taxon->getIconType(), 'icon');
    }

    /**
     * @Then the pictogram of the taxon with code :code should be stored in the application public directory
     */
    public function thePictogramOfTheTaxonWithCodeShouldBeStoredInTheApplicationPublicDirectory(string $code): void
    {
        $taxon = $this->getTaxon($code);
        $storedPath = (string) $taxon->getIcon();

        Assert::startsWith(
            $storedPath,
            TaxonIconUploader::PUBLIC_PREFIX . '/',
            sprintf('The pictogram of the taxon "%s" is not stored in the application public directory: "%s".', $code, $storedPath),
        );
        Assert::same($taxon->getIconType(), 'image');
        Assert::fileExists(
            $this->taxonIconUploader->getDirectory() . '/' . basename($storedPath),
            sprintf('The pictogram file of the taxon "%s" is missing.', $code),
        );
    }

    private function getTaxon(string $code): Taxon
    {
        $taxon = $this->taxonRepository->findOneBy(['code' => $code]);
        Assert::isInstanceOf($taxon, Taxon::class, sprintf('The taxon with code "%s" was not found.', $code));

        return $taxon;
    }

    /**
     * @When I want to edit the taxon with code :code
     */
    public function iWantToEditTheTaxonWithCode(string $code): void
    {
        $taxonId = $this->getTaxon($code)->getId();
        Assert::scalar($taxonId);

        $this->getSession()->visit($this->locatePath(sprintf('/admin/taxons/%d/edit', (int) $taxonId)));
    }

    /**
     * @Given the taxon with code :code has a media in the :zone zone at position :position
     */
    public function theTaxonWithCodeHasAMediaInTheZoneAtPosition(string $code, string $zone, int $position): void
    {
        $taxon = $this->getTaxon($code);

        $media = new TaxonImage();
        $media->setType($zone);
        $media->setPosition($position);
        $media->setPath($this->findAvailableMediaPath());

        $taxon->addImage($media);
        $this->taxonRepository->add($taxon);
    }

    /**
     * @Then the :zone zone should not offer the card text option
     */
    public function theZoneShouldNotOfferTheCardTextOption(string $zone): void
    {
        Assert::false(
            str_contains($this->getZonePrototype($zone), '[showCardText]'),
            sprintf('The "%s" zone must not offer the card text option.', $zone),
        );
    }

    /**
     * @Then the :zone zone should offer the card text option
     */
    public function theZoneShouldOfferTheCardTextOption(string $zone): void
    {
        Assert::true(
            str_contains($this->getZonePrototype($zone), '[showCardText]'),
            sprintf('The "%s" zone must offer the card text option.', $zone),
        );
    }

    /**
     * @Then the next position offered by the :zone zone should be :position
     */
    public function theNextPositionOfferedByTheZoneShouldBe(string $zone, int $position): void
    {
        $prototype = $this->getZonePrototype($zone);

        Assert::same(
            1,
            preg_match('/<input[^>]*\[position\][^>]*\svalue="(?<position>\d+)"/', $prototype, $matches),
            sprintf('The "%s" zone does not pre-fill any media position.', $zone),
        );

        Assert::same((string) $position, $matches['position'] ?? '', sprintf('Unexpected pre-filled position for the "%s" zone.', $zone));
    }

    /**
     * @Then the :zone zone media positions should be :positions
     */
    public function theZoneMediaPositionsShouldBe(string $zone, string $positions): void
    {
        $listedPositions = [];
        foreach ($this->getZoneCollection($zone)->findAll('css', '[data-form-collection="list"] input[name$="[position]"]') as $input) {
            $value = $input->getValue();
            Assert::scalar($value, sprintf('The "%s" zone holds a non scalar media position.', $zone));

            $listedPositions[] = (string) $value;
        }

        Assert::same(
            explode(',', $positions),
            $listedPositions,
            sprintf('Unexpected positions for the "%s" zone media, found "%s".', $zone, implode(',', $listedPositions)),
        );
    }

    /**
     * The prototype holds the media markup added by the "add" button, so it reflects the fields
     * and the default values offered by a zone.
     */
    private function getZonePrototype(string $zone): string
    {
        $prototype = $this->getZoneCollection($zone)->getAttribute('data-prototype');
        Assert::notNull($prototype, sprintf('The "%s" zone does not hold any media prototype.', $zone));

        return $prototype;
    }

    private function getZoneCollection(string $zone): NodeElement
    {
        $collection = $this->getSession()->getPage()->find(
            'css',
            sprintf('.media-collection-%s[data-form-type="collection"]', str_replace('_', '-', $zone)),
        );
        Assert::isInstanceOf($collection, NodeElement::class, sprintf('The "%s" zone media collection was not found.', $zone));

        return $collection;
    }

    /**
     * Writes the pictogram fixture to a temporary file, as the browser needs a real file to upload.
     */
    private function createPictogramFile(string $filename): string
    {
        $path = sprintf('%s/%s.%s', sys_get_temp_dir(), uniqid('pictogram_', true), pathinfo($filename, \PATHINFO_EXTENSION) ?: 'png');

        Assert::notFalse(file_put_contents($path, (string) base64_decode(self::PICTOGRAM_CONTENT, true)));

        return $path;
    }

    /**
     * The admin preview runs the media through the image pipeline, so the media must hold a path
     * pointing to a file that actually exists.
     */
    private function findAvailableMediaPath(): string
    {
        $files = glob(sprintf('%s/public/media/image/*/*/*.*', $this->projectDir)) ?: [];
        Assert::notEmpty($files, 'No media file is available to attach to the taxon.');

        $file = $files[0];

        return sprintf(
            '%s/%s/%s',
            basename(\dirname($file, 2)),
            basename(\dirname($file)),
            basename($file),
        );
    }
}
