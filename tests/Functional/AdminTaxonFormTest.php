<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Functional;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\HttpFoundation\Response;

final class AdminTaxonFormTest extends AdminTestCase
{
    public function test_the_condition_references_are_listed_with_their_label(): void
    {
        // Unique: a taxon of the same name left in the database (a Behat scenario does not clean up after itself) would get its code appended.
        $name = uniqid('Watches ', false);
        $taxon = $this->createTaxon($name);
        $taxonId = $taxon->getId();
        self::assertIsInt($taxonId);

        $crawler = $this->client->request('GET', sprintf('/admin/taxons/%d/edit', $taxonId));
        self::assertResponseIsSuccessful();

        $choices = json_decode((string) $crawler->filter('[data-at-admin-taxon-facet-conditions-choices-value]')->attr('data-at-admin-taxon-facet-conditions-choices-value'), true);
        self::assertIsArray($choices);
        self::assertIsArray($choices['taxon_membership']);
        self::assertSame($taxon->getCode(), $choices['taxon_membership'][$name] ?? null);

        $messages = json_decode((string) $crawler->filter('[data-at-admin-taxon-facet-conditions-messages-value]')->attr('data-at-admin-taxon-facet-conditions-messages-value'), true);
        self::assertIsArray($messages);
        self::assertSame('%count% product(s) match the criteria.', $messages['matching'] ?? null);
    }

    public function test_references_sharing_a_label_stay_selectable(): void
    {
        $first = $this->createTaxon('Accessories');
        $second = $this->createTaxon('Accessories');
        $firstId = $first->getId();
        self::assertIsInt($firstId);

        $crawler = $this->client->request('GET', sprintf('/admin/taxons/%d/edit', $firstId));
        self::assertResponseIsSuccessful();

        $choices = json_decode((string) $crawler->filter('[data-at-admin-taxon-facet-conditions-choices-value]')->attr('data-at-admin-taxon-facet-conditions-choices-value'), true);
        self::assertIsArray($choices);
        self::assertIsArray($choices['taxon_membership']);
        self::assertContains($first->getCode(), $choices['taxon_membership']);
        self::assertContains($second->getCode(), $choices['taxon_membership']);
    }

    public function test_the_taxon_form_saves_a_new_taxon_with_the_plugin_defaults(): void
    {
        $crawler = $this->client->request('GET', '/admin/taxons/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="sylius_admin_taxon"]')->form();
        $values = $form->getPhpValues();
        self::assertIsArray($values['sylius_admin_taxon']);
        $code = uniqid('at_saved_', false);
        $values['sylius_admin_taxon']['code'] = $code;
        $values['sylius_admin_taxon']['translations'] = ['en_US' => ['name' => 'Saved', 'slug' => $code]];

        $this->client->request($form->getMethod(), $form->getUri(), $values, $form->getPhpFiles());

        // Registered for removal before any assertion: a failing one must not leave the taxon behind.
        $taxon = $this->entityManager->getRepository(TaxonInterface::class)->findOneBy(['code' => $code]);
        if ($taxon !== null) {
            $this->removeAfterTest($taxon);
        }

        self::assertResponseRedirects();
        self::assertInstanceOf(AdvancedTaxonInterface::class, $taxon);

        self::assertNull($taxon->getColor());
        self::assertNull($taxon->getIcon());
        self::assertFalse($taxon->isUniverse());
        self::assertFalse($taxon->isConditional());
        self::assertFalse($taxon->isAdvancedFiltersEnabled());
        self::assertSame('before_filters', $taxon->getFeaturedProductsPosition());
        self::assertSame('stacked', $taxon->getMediaDisplayModeTop());
        self::assertCount(0, $taxon->getImages());
    }

    public function test_an_incomplete_facet_condition_is_reported_and_not_saved(): void
    {
        $taxon = $this->createTaxon('Watches');

        $taxonId = $taxon->getId();
        self::assertIsInt($taxonId);

        $crawler = $this->client->request('GET', sprintf('/admin/taxons/%d/edit', $taxonId));
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="sylius_admin_taxon"]')->form();
        $values = $form->getPhpValues();
        self::assertIsArray($values['sylius_admin_taxon']);
        $values['sylius_admin_taxon']['facetConditions'] = [
            ['conditionType' => 'attribute', 'operator' => 'contains', 'referenceCode' => '', 'value' => 'steel', 'position' => '0'],
        ];

        $this->client->request($form->getMethod(), $form->getUri(), $values, $form->getPhpFiles());

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertSelectorTextContains(
            '[data-test-facet-condition-errors]',
            'Select the attribute, option or taxon this condition applies to.',
        );

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find($taxon::class, $taxonId);
        self::assertNotNull($reloaded);
        self::assertCount(0, $reloaded->getFacetConditions());
    }
}
