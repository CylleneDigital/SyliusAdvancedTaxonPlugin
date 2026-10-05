<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Functional;

use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\HttpFoundation\Response;
use Webmozart\Assert\Assert;

final class AdminFacetPreviewControllerTest extends AdminTestCase
{
    private const URL = '/admin/advanced-taxon/facet-conditions/preview';

    public function test_it_previews_the_products_matching_the_complete_conditions(): void
    {
        $name = uniqid('Preview watch ', false);
        $product = $this->createProduct($name);

        $this->preview($this->csrfToken(), [
            ['conditionType' => 'name', 'operator' => 'equals', 'value' => $name],
            // A row still being filled in is ignored.
            ['conditionType' => 'attribute', 'operator' => 'equals', 'referenceCode' => '', 'value' => ''],
        ]);

        self::assertResponseIsSuccessful();
        $payload = $this->payload();
        self::assertSame(1, $payload['count']);
        $productId = $product->getId();
        self::assertIsInt($productId);
        self::assertSame(
            [['id' => $productId, 'code' => $product->getCode(), 'name' => $name, 'url' => sprintf('/admin/products/%d', $productId)]],
            $payload['products'],
        );
    }

    public function test_it_matches_nothing_without_a_complete_condition(): void
    {
        $this->createProduct(uniqid('Preview watch ', false));

        $this->preview($this->csrfToken(), [['conditionType' => 'name', 'operator' => 'contains', 'value' => '']]);

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->payload()['count']);
    }

    public function test_it_ignores_malformed_conditions_instead_of_failing(): void
    {
        $name = uniqid('Preview watch ', false);
        $this->createProduct($name);

        $this->send($this->csrfToken(), (string) json_encode(['conditions' => [
            'named' => ['conditionType' => 'name', 'operator' => 'contains', 'value' => ['not', 'a', 'string']],
            'scalar',
            ['conditionType' => ['name'], 'operator' => 'contains', 'value' => 'x'],
            ['conditionType' => 'name', 'operator' => 'equals', 'value' => $name],
        ]]));

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->payload()['count']);
    }

    public function test_an_invalid_json_body_matches_nothing(): void
    {
        $this->createProduct(uniqid('Preview watch ', false));

        $this->send($this->csrfToken(), '{"conditions": [');

        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->payload()['count']);
    }

    public function test_it_counts_every_match_but_returns_twenty_products_at_most(): void
    {
        $prefix = uniqid('Preview limit ', false);
        for ($i = 0; $i < 25; ++$i) {
            $this->createProduct(sprintf('%s %02d', $prefix, $i));
        }

        $this->preview($this->csrfToken(), [['conditionType' => 'name', 'operator' => 'contains', 'value' => $prefix]]);

        self::assertResponseIsSuccessful();
        $payload = $this->payload();
        self::assertSame(25, $payload['count']);
        self::assertCount(20, $payload['products']);
        self::assertSame(20, $payload['limit']);
    }

    public function test_it_only_applies_the_first_thirty_two_conditions(): void
    {
        $name = uniqid('Preview watch ', false);
        $this->createProduct($name);

        $conditions = array_fill(0, 32, ['conditionType' => 'name', 'operator' => 'contains', 'value' => $name]);
        // Would match nothing if it were applied.
        $conditions[] = ['conditionType' => 'name', 'operator' => 'equals', 'value' => 'no product has this name'];

        $this->preview($this->csrfToken(), $conditions);

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->payload()['count']);
    }

    public function test_it_is_reserved_to_the_back_office_users(): void
    {
        $csrfToken = $this->csrfToken();
        $this->client->getCookieJar()->clear();

        $this->preview($csrfToken, [['conditionType' => 'stock', 'operator' => 'is_in_stock']]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/admin/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function test_it_rejects_a_request_without_a_valid_csrf_token(): void
    {
        $this->preview('forged', [['conditionType' => 'stock', 'operator' => 'is_in_stock']]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function csrfToken(): string
    {
        $taxonId = $this->createTaxon('Preview')->getId();
        self::assertIsInt($taxonId);

        $crawler = $this->client->request('GET', sprintf('/admin/taxons/%d/edit', $taxonId));
        self::assertResponseIsSuccessful();

        return (string) $crawler->filter('[data-at-admin-taxon-facet-conditions-csrf-token-value]')->attr('data-at-admin-taxon-facet-conditions-csrf-token-value');
    }

    /**
     * @param list<array<string, string>> $conditions
     */
    private function preview(string $csrfToken, array $conditions): void
    {
        $this->send($csrfToken, (string) json_encode(['conditions' => $conditions]));
    }

    private function send(string $csrfToken, string $body): void
    {
        $this->client->request(
            'POST',
            self::URL,
            server: ['HTTP_X_CSRF_TOKEN' => $csrfToken, 'CONTENT_TYPE' => 'application/json'],
            content: $body,
        );
    }

    /**
     * @return array{count: int, products: list<array<string, mixed>>, limit: int}
     */
    private function payload(): array
    {
        /** @var array{count: int, products: list<array<string, mixed>>, limit: int} $payload */
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function createProduct(string $name): ProductInterface
    {
        $product = $this->factory('sylius.factory.product')->createNew();
        Assert::isInstanceOf($product, ProductInterface::class);

        $code = uniqid('at_product_', false);
        $product->setCode($code);
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setName($name);
        $product->setSlug($code);

        return $this->persist($product);
    }
}
