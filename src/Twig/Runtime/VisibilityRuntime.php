<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Featured products and taxons are stored as picked in the back office: a product disabled or
 * removed from the channel since then, or a disabled taxon, must not reach the storefront.
 */
final class VisibilityRuntime implements RuntimeExtensionInterface, ResetInterface
{
    /**
     * Channel membership of the products already checked, by "channel code/product id": the same
     * featured products are checked by several templates of a page.
     *
     * @var array<string, bool>
     */
    private array $membership = [];

    /**
     * @param class-string<ProductInterface> $productClass
     */
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $productClass,
    ) {
    }

    /**
     * @param iterable<mixed> $products
     *
     * @return list<ProductInterface> the enabled products available in the current channel
     */
    public function visibleProducts(iterable $products): array
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            $channel = null;
        }

        $enabled = [];
        foreach ($products as $product) {
            if ($product instanceof ProductInterface && $product->isEnabled()) {
                $enabled[] = $product;
            }
        }

        if ($channel === null) {
            return $enabled;
        }

        $inChannel = $this->checkUnloadedChannels($enabled, $channel);

        return array_values(array_filter(
            $enabled,
            static fn (ProductInterface $product): bool => $inChannel[spl_object_id($product)] ?? $product->hasChannel($channel),
        ));
    }

    /**
     * The channels of the products not loaded yet are checked in one query, instead of one per
     * product.
     *
     * @param list<ProductInterface> $products
     *
     * @return array<int, bool> whether each product whose channels are not loaded belongs to the
     *                          channel, by object id
     */
    private function checkUnloadedChannels(array $products, ChannelInterface $channel): array
    {
        $inChannel = [];
        $unloaded = [];
        foreach ($products as $product) {
            $channels = $product->getChannels();
            $id = $product->getId();
            if (!$channels instanceof PersistentCollection || $channels->isInitialized() || !is_int($id)) {
                continue;
            }

            $key = $channel->getCode() . '/' . $id;
            if (isset($this->membership[$key])) {
                $inChannel[spl_object_id($product)] = $this->membership[$key];
            } else {
                $unloaded[$id] = spl_object_id($product);
            }
        }

        if ($unloaded === []) {
            return $inChannel;
        }

        /** @var list<int|string> $ids */
        $ids = $this->entityManager->createQueryBuilder()
            ->select('product.id')
            ->from($this->productClass, 'product')
            ->innerJoin('product.channels', 'channel')
            ->andWhere('channel = :channel')
            ->andWhere('product.id IN (:ids)')
            ->setParameter('channel', $channel)
            ->setParameter('ids', array_keys($unloaded))
            ->getQuery()
            ->getSingleColumnResult();

        $found = array_fill_keys(array_map('intval', $ids), true);
        foreach ($unloaded as $id => $objectId) {
            $inChannel[$objectId] = $this->membership[$channel->getCode() . '/' . $id] = isset($found[$id]);
        }

        return $inChannel;
    }

    /**
     * @param iterable<mixed> $taxons
     *
     * @return list<TaxonInterface> the enabled taxons
     */
    public function visibleTaxons(iterable $taxons): array
    {
        $visible = [];
        foreach ($taxons as $taxon) {
            if ($taxon instanceof TaxonInterface && $taxon->isEnabled()) {
                $visible[] = $taxon;
            }
        }

        return $visible;
    }

    #[\Override]
    public function reset(): void
    {
        $this->membership = [];
    }
}
