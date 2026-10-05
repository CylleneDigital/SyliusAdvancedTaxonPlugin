<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Behat\Element\Admin\Taxon;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use FriendsOfBehat\PageObjectExtension\Element\Element;
use Webmozart\Assert\Assert;

/**
 * The plugin part of the admin taxon form, the same on the create and the update pages. Its fields
 * live in Bootstrap tabs and accordions: in a browser, a field is only reachable once they are open.
 */
final class AdvancedTaxonFormElement extends Element
{
    private const FORM_NAME = 'sylius_admin_taxon';

    private const BROWSER_TIMEOUT_MS = 5000;

    public function setTranslatedField(string $field, string $localeCode, string $value): void
    {
        $this->getDocument()->fillField(sprintf('%s[translations][%s][%s]', self::FORM_NAME, $localeCode, $field), $value);
    }

    public function setColor(string $color): void
    {
        $this->getDocument()->checkField(self::FORM_NAME . '[hasColor]');
        $this->getDocument()->fillField(self::FORM_NAME . '[color]', $color);
    }

    public function chooseIcon(string $icon): void
    {
        $this->getDocument()->selectFieldOption(self::FORM_NAME . '[iconType]', 'icon');
        $this->getDocument()->fillField(self::FORM_NAME . '[icon]', $icon);
    }

    public function uploadPictogram(string $path): void
    {
        $this->getDocument()->selectFieldOption(self::FORM_NAME . '[iconType]', 'image');
        $this->getElement('pictogram_file')->attachFile($path);
    }

    public function pickIconInPicker(string $icon): void
    {
        $this->openTab('display');
        $this->getElement('icon_picker_button')->click();
        $this->waitFor('document.querySelector("#advanced-taxon-icon-modal")?.classList.contains("show")');

        $this->getElement('icon_picker_icon', ['%icon%' => $icon])->click();
        $this->waitFor('!document.querySelector("#advanced-taxon-icon-modal")?.classList.contains("show")');
    }

    public function getIcon(): string
    {
        $value = $this->getDocument()->findField(self::FORM_NAME . '[icon]')?->getValue();
        Assert::nullOrString($value);

        return (string) $value;
    }

    public function enableFeaturedProductsAsSlider(string $position): void
    {
        $document = $this->getDocument();
        $document->checkField(self::FORM_NAME . '[isFeaturedProductsActive]');
        $document->selectFieldOption(self::FORM_NAME . '[featuredProductsPosition]', $position);
        $document->checkField(self::FORM_NAME . '[isSlider]');
    }

    public function makeUniverse(string $localeCode, string $title): void
    {
        $this->getDocument()->checkField(self::FORM_NAME . '[isUniverse]');
        $this->getDocument()->fillField(
            sprintf('%s[featuredItemsTranslations][%s][universePageTitle]', self::FORM_NAME, $localeCode),
            $title,
        );
    }

    /**
     * The prototype holds the markup the "add" button inserts: the fields and the default values a
     * zone offers.
     */
    public function getZonePrototype(string $zone): string
    {
        $prototype = $this->getZone($zone)->getAttribute('data-prototype');
        Assert::notNull($prototype, sprintf('The "%s" zone does not hold any media prototype.', $zone));

        return $prototype;
    }

    /**
     * @return list<string>
     */
    public function getZoneMediaPositions(string $zone): array
    {
        $positions = [];
        foreach ($this->getZone($zone)->findAll('css', '[data-form-collection="list"] input[name$="[position]"]') as $input) {
            $value = $input->getValue();
            Assert::string($value);
            $positions[] = $value;
        }

        return $positions;
    }

    /**
     * @return list<string>
     */
    public function getZoneMediaFieldNames(string $zone): array
    {
        return array_values(array_map(
            static fn (NodeElement $input): string => (string) $input->getAttribute('name'),
            $this->getZone($zone)->findAll('css', '[data-form-collection="list"] input[name$="[position]"]'),
        ));
    }

    public function addMedia(string $zone): void
    {
        $this->showZone($zone);
        $this->find($this->getZone($zone), '[data-form-collection="add"]')->click();
    }

    public function removeFirstMedia(string $zone): void
    {
        $this->showZone($zone);
        $this->find($this->getZone($zone), '[data-form-collection="item"] [data-form-collection="delete"]')->click();
    }

    public function addNameCondition(string $value): void
    {
        $row = $this->addCondition();
        $this->find($row, '[data-facet-type]')->selectOption('name');
        $this->find($row, '[data-facet-operator]')->selectOption('contains');
        $this->find($row, '[data-facet-value]')->setValue($value);
    }

    public function addAttributeConditionWithoutAttribute(string $value): void
    {
        $row = $this->addCondition();
        $this->find($row, '[data-facet-type]')->selectOption('attribute');
        $this->find($row, '[data-facet-operator]')->selectOption('contains');
        $this->find($row, '[data-facet-value]')->setValue($value);
    }

    public function testConditions(): void
    {
        $this->getElement('preview_button')->click();
    }

    public function waitForPreviewSummary(string $summary): void
    {
        $this->waitFor(sprintf(
            'document.querySelector(%s)?.textContent.trim() === %s',
            json_encode('[data-at-admin-taxon-facet-conditions-target="previewSummary"]'),
            json_encode($summary),
        ));
    }

    public function countPreviewedProducts(): int
    {
        return count($this->getDocument()->findAll('css', '#facet-preview-modal [data-at-admin-taxon-facet-conditions-target="previewList"] > li'));
    }

    /**
     * @return list<string> the tabs flagged as holding an error, by their target pane
     */
    public function getTabsInError(): array
    {
        return array_values(array_map(
            static fn (NodeElement $tab): string => (string) $tab->getAttribute('data-bs-target'),
            $this->getDocument()->findAll('css', '.at-admin-tab-invalid'),
        ));
    }

    public function getConditionErrors(): string
    {
        return implode(' ', array_map(
            static fn (NodeElement $errors): string => $errors->getText(),
            $this->getDocument()->findAll('css', '[data-test-facet-condition-errors]'),
        ));
    }

    /**
     * @return array<string, string>
     */
    protected function getDefinedElements(): array
    {
        return [
            'icon_picker_button' => '#taxon-tab-display [data-icon-picker-open]',
            'icon_picker_icon' => '#advanced-taxon-icon-modal [data-icon-value="%icon%"]',
            'pictogram_file' => sprintf('input[name="%s[iconFile]"]', self::FORM_NAME),
            'preview_button' => '[data-action~="click->at-admin-taxon-facet-conditions#previewConditions"]',
        ];
    }

    private function addCondition(): NodeElement
    {
        $this->openTab('virtual-conditions');
        $this->find($this->getDocument(), '[data-test-add-condition]')->click();

        $rows = $this->getDocument()->findAll('css', '[data-facet-condition-row]');
        $row = end($rows);
        Assert::isInstanceOf($row, NodeElement::class, 'No condition row was added.');

        return $row;
    }

    private function getZone(string $zone): NodeElement
    {
        return $this->find($this->getDocument(), sprintf('.media-collection-%s[data-form-type="collection"]', str_replace('_', '-', $zone)));
    }

    private function showZone(string $zone): void
    {
        $this->openTab($zone === 'slider_universe' ? 'universe' : 'advanced-medias');

        $collapse = sprintf('#advanced-taxon-media-%s-collapse', str_replace('_', '-', $zone));
        if (!$this->find($this->getDocument(), $collapse)->hasClass('show')) {
            $this->find($this->getDocument(), sprintf('[data-bs-target="%s"]', $collapse))->click();
            $this->waitFor(sprintf('document.querySelector(%s)?.classList.contains("show")', json_encode($collapse)));
        }
    }

    private function openTab(string $tab): void
    {
        $pane = sprintf('#taxon-tab-%s', $tab);
        $this->find($this->getDocument(), sprintf('[data-bs-toggle="tab"][data-bs-target="%s"]', $pane))->click();
        $this->waitFor(sprintf('document.querySelector(%s)?.classList.contains("show")', json_encode($pane)));
    }

    private function waitFor(string $condition): void
    {
        Assert::true($this->getSession()->wait(self::BROWSER_TIMEOUT_MS, $condition), sprintf('Timed out waiting for: %s', $condition));
    }

    private function find(DocumentElement|NodeElement $container, string $selector): NodeElement
    {
        $element = $container->find('css', $selector);
        Assert::isInstanceOf($element, NodeElement::class, sprintf('The element "%s" could not be found.', $selector));

        return $element;
    }
}
