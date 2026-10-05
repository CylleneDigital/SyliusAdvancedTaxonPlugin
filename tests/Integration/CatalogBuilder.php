<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Integration;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Attribute\Factory\AttributeFactoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Product\Model\ProductOptionInterface;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webmozart\Assert\Assert;

/**
 * Builds a small catalog through the Sylius factories. Every code is unique, so the records never
 * collide with the ones of another test, and the caller rolls the transaction back afterwards.
 */
final class CatalogBuilder
{
    private readonly string $suffix;

    private ?LocaleInterface $locale = null;

    private ?CurrencyInterface $currency = null;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->suffix = uniqid('', false);
    }

    public function channel(string $code): ChannelInterface
    {
        $channel = $this->create('sylius.factory.channel', ChannelInterface::class);
        $channel->setCode($this->code($code));
        $channel->setName($code);
        $channel->setTaxCalculationStrategy('order_items_based');
        $channel->setDefaultLocale($this->locale());
        $channel->addLocale($this->locale());
        $channel->setBaseCurrency($this->currency());

        return $this->save($channel);
    }

    public function taxon(string $name, ?AdvancedTaxonInterface $parent = null): AdvancedTaxonInterface
    {
        $taxon = $this->create('sylius.factory.taxon', AdvancedTaxonInterface::class);
        $parent?->addChild($taxon);
        $taxon->setCode($this->code($name));
        $taxon->setCurrentLocale('en_US');
        $taxon->setFallbackLocale('en_US');
        $taxon->setName($name);
        $taxon->setSlug($this->code($name));

        return $this->save($taxon);
    }

    /**
     * @param list<ChannelInterface> $channels
     */
    public function product(string $name, array $channels, ?AdvancedTaxonInterface $taxon = null): ProductInterface
    {
        $product = $this->create('sylius.factory.product', ProductInterface::class);
        $product->setCode($this->code($name));
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setName($name);
        $product->setSlug($this->code($name));
        foreach ($channels as $channel) {
            $product->addChannel($channel);
        }

        if ($taxon !== null) {
            $productTaxon = $this->create('sylius.factory.product_taxon', ProductTaxonInterface::class);
            $productTaxon->setTaxon($taxon);
            $product->addProductTaxon($productTaxon);
        }

        return $this->save($product);
    }

    public function assign(ProductInterface $product, AdvancedTaxonInterface $taxon): void
    {
        $productTaxon = $this->create('sylius.factory.product_taxon', ProductTaxonInterface::class);
        $productTaxon->setTaxon($taxon);
        $product->addProductTaxon($productTaxon);

        $this->save($product);
    }

    /**
     * @param list<ProductOptionValueInterface> $optionValues
     */
    public function variant(
        ProductInterface $product,
        ChannelInterface $channel,
        int $price,
        array $optionValues = [],
        int $onHand = 10,
        int $onHold = 0,
        bool $tracked = true,
    ): ProductVariantInterface {
        $variant = $this->create('sylius.factory.product_variant', ProductVariantInterface::class);
        $variant->setCode($this->code((string) $product->getCode() . '_variant_' . count($product->getVariants())));
        $variant->setOnHand($onHand);
        $variant->setOnHold($onHold);
        $variant->setTracked($tracked);
        foreach ($optionValues as $optionValue) {
            $variant->addOptionValue($optionValue);
        }

        $channelPricing = $this->create('sylius.factory.channel_pricing', ChannelPricingInterface::class);
        $channelPricing->setChannelCode($channel->getCode());
        $channelPricing->setPrice($price);
        $variant->addChannelPricing($channelPricing);

        $product->addVariant($variant);

        return $this->save($variant);
    }

    /**
     * @param list<string> $values
     *
     * @return array<string, ProductOptionValueInterface> option values by value
     */
    public function option(string $code, string $name, array $values): array
    {
        $option = $this->create('sylius.factory.product_option', ProductOptionInterface::class);
        $option->setCode($this->code($code));
        $option->setCurrentLocale('en_US');
        $option->setFallbackLocale('en_US');
        $option->setName($name);

        $optionValues = [];
        foreach ($values as $value) {
            $optionValue = $this->create('sylius.factory.product_option_value', ProductOptionValueInterface::class);
            $optionValue->setCode($this->code($code . '_' . $value));
            $optionValue->setCurrentLocale('en_US');
            $optionValue->setFallbackLocale('en_US');
            $optionValue->setValue($value);
            $option->addValue($optionValue);
            $optionValues[$value] = $optionValue;
        }

        $this->save($option);

        return $optionValues;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function attribute(string $code, string $type, string $name, array $configuration = []): ProductAttributeInterface
    {
        $factory = $this->container->get('sylius.factory.product_attribute');
        Assert::isInstanceOf($factory, AttributeFactoryInterface::class);

        $attribute = $factory->createTyped($type);
        Assert::isInstanceOf($attribute, ProductAttributeInterface::class);
        $attribute->setCode($this->code($code));
        $attribute->setCurrentLocale('en_US');
        $attribute->setFallbackLocale('en_US');
        $attribute->setName($name);
        $attribute->setConfiguration($configuration);
        $attribute->setTranslatable($type === 'text');

        return $this->save($attribute);
    }

    public function attributeValue(ProductInterface $product, ProductAttributeInterface $attribute, mixed $value, ?string $localeCode = null): void
    {
        $attributeValue = $this->create('sylius.factory.product_attribute_value', ProductAttributeValueInterface::class);
        $attributeValue->setAttribute($attribute);
        $attributeValue->setLocaleCode($localeCode);
        $attributeValue->setValue($value);
        $product->addAttribute($attributeValue);

        $this->save($attributeValue);
    }

    public function code(string $name): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $name)) . '_' . $this->suffix;
    }

    public function locale(): LocaleInterface
    {
        if ($this->locale !== null) {
            return $this->locale;
        }

        $localeClass = $this->container->getParameter('sylius.model.locale.class');
        Assert::string($localeClass);
        Assert::classExists($localeClass);

        $locale = $this->entityManager->getRepository($localeClass)->findOneBy(['code' => 'en_US']);
        if (!$locale instanceof LocaleInterface) {
            $locale = $this->create('sylius.factory.locale', LocaleInterface::class);
            $locale->setCode('en_US');
            $this->save($locale);
        }

        return $this->locale = $locale;
    }

    private function currency(): CurrencyInterface
    {
        if ($this->currency !== null) {
            return $this->currency;
        }

        // XTS is the ISO 4217 code reserved for testing: shared by the tests, created once.
        $repository = $this->container->get('sylius.repository.currency');
        Assert::isInstanceOf($repository, RepositoryInterface::class);
        $currency = $repository->findOneBy(['code' => 'XTS']);
        if (!$currency instanceof CurrencyInterface) {
            $currency = $this->create('sylius.factory.currency', CurrencyInterface::class);
            $currency->setCode('XTS');
            $this->save($currency);
        }

        return $this->currency = $currency;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function create(string $factoryId, string $class): object
    {
        $factory = $this->container->get($factoryId);
        Assert::isInstanceOf($factory, FactoryInterface::class);

        $resource = $factory->createNew();
        Assert::isInstanceOf($resource, $class);

        return $resource;
    }

    /**
     * @template T of object
     *
     * @param T $record
     *
     * @return T
     */
    private function save(object $record): object
    {
        $this->entityManager->persist($record);
        $this->entityManager->flush();

        return $record;
    }
}
