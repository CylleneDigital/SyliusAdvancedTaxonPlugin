<?php

declare(strict_types=1);

namespace Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Unit\Twig\Runtime;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\UniverseRuntime;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Runtime\VisibilityRuntime;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\Product;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\Taxon;
use Tests\CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\TaxonImage;

final class UniverseRuntimeTest extends TestCase
{
    public function test_the_universe_data_fall_back_on_the_name_and_the_default_colors(): void
    {
        $taxon = $this->taxon('Watches');
        $taxon->setUniverseBackgroundUseTaxonColor(true);

        self::assertSame([
            'useTaxonColorBackground' => false,
            'titleBackgroundEnabled' => false,
            'universeButtonColor' => '#0d6efd',
            'universeAccentColor' => '#ffffff',
            'universePageTitle' => 'Watches',
            'universePageDescription' => null,
            'universeFeaturedTitle' => null,
            'universeFeaturedDescription' => null,
        ], $this->runtime()->getUniverseData($taxon));
    }

    public function test_the_universe_data_use_the_taxon_color_and_texts(): void
    {
        $taxon = $this->taxon('Watches');
        $taxon->setColor('#336699');
        $taxon->setUniverseBackgroundUseTaxonColor(true);
        $taxon->setUniverseTitleBackgroundEnabled(true);
        $taxon->getOrCreateFeaturedItemsTranslation('en_US')->setUniversePageTitle('Time');

        $data = $this->runtime()->getUniverseData($taxon);

        self::assertTrue($data['useTaxonColorBackground']);
        self::assertTrue($data['titleBackgroundEnabled']);
        self::assertSame('#336699', $data['universeButtonColor']);
        self::assertSame('Time', $data['universePageTitle']);
    }

    public function test_the_stacked_featured_media_is_the_one_with_the_lowest_position(): void
    {
        $taxon = $this->taxon('Watches');
        $taxon->addImage($this->media('featured', 'second.png', 2));
        $taxon->addImage($this->media('featured', 'first.png', 1));
        $taxon->addImage($this->media('top', 'top.png', 0));
        $taxon->addImage($this->media('featured', '', 0));

        self::assertSame('/media/sylius_original/first.png', $this->runtime()->getFeaturedMediaUrl($taxon));
    }

    public function test_the_random_featured_media_is_one_of_the_zone(): void
    {
        $taxon = $this->taxon('Watches');
        $taxon->setMediaDisplayModeFeatured('random');
        $taxon->addImage($this->media('featured', 'first.png', 1));
        $taxon->addImage($this->media('featured', 'second.png', 2));
        $taxon->addImage($this->media('top', 'top.png', 0));

        $runtime = $this->runtime();
        $urls = [];
        for ($i = 0; $i < 64; ++$i) {
            $urls[(string) $runtime->getFeaturedMediaUrl($taxon)] = true;
        }
        ksort($urls);

        // Both media come up (a stacked zone would always give the first one).
        self::assertSame(['/media/sylius_original/first.png', '/media/sylius_original/second.png'], array_keys($urls));
        self::assertNull($this->runtime()->getFeaturedMediaUrl($this->taxon('Empty')));
    }

    public function test_the_slider_and_bottom_media_are_sorted_by_position(): void
    {
        $taxon = $this->taxon('Watches');
        $late = $this->media('slider_universe', 'late.png', 5);
        $early = $this->media('slider_universe', 'early.png', 1);
        $bottom = $this->media('bottom', 'bottom.png', 0);
        $taxon->addImage($late);
        $taxon->addImage($early);
        $taxon->addImage($bottom);

        self::assertSame([$early, $late], $this->runtime()->getUniverseSliderMedia($taxon));
        self::assertSame([$bottom], $this->runtime()->getUniverseBottomMedia($taxon));
    }

    public function test_the_mobile_url_uses_the_original_image_without_the_mobile_filter(): void
    {
        self::assertSame('/media/sylius_original/hero.png', $this->runtime()->getMediaMobileUrl('hero.png'));
        self::assertSame('/media/cyllene_universe_slider_mobile/hero.png', $this->runtime(['cyllene_universe_slider_mobile' => []])->getMediaMobileUrl('/hero.png'));
        self::assertNull($this->runtime()->getMediaUrl(''));
    }

    public function test_an_unsafe_destination_url_never_reaches_the_storefront(): void
    {
        self::assertNull($this->runtime()->getMediaHref('javascript:alert(1)'));
        self::assertSame('/sale', $this->runtime()->getMediaHref(' /sale '));
    }

    public function test_it_tells_whether_a_child_shows_featured_products(): void
    {
        $quiet = $this->taxon('Quiet');
        $showing = $this->taxon('Showing');
        $showing->setIsFeaturedProductsActive(true);
        $product = new Product();
        $showing->addFeaturedProduct($product);

        self::assertFalse($this->runtime()->hasFeaturedChildrenProducts([$quiet]));
        self::assertTrue($this->runtime()->hasFeaturedChildrenProducts([$quiet, $showing]));

        $product->setEnabled(false);
        self::assertFalse($this->runtime()->hasFeaturedChildrenProducts([$showing]));
    }

    /**
     * @param array<string, array<string, mixed>> $filterSets
     */
    private function runtime(array $filterSets = []): UniverseRuntime
    {
        $cacheManager = $this->createStub(CacheManager::class);
        $cacheManager->method('getBrowserPath')->willReturnCallback(
            static fn (string $path, string $filter): string => sprintf('/media/%s/%s', $filter, $path),
        );

        $channelContext = $this->createStub(ChannelContextInterface::class);
        $channelContext->method('getChannel')->willThrowException(new ChannelNotFoundException());

        return new UniverseRuntime(
            new VisibilityRuntime($channelContext, $this->createStub(EntityManagerInterface::class), Product::class),
            $cacheManager,
            new FilterConfiguration(['sylius_original' => []] + $filterSets),
        );
    }

    private function taxon(string $name): Taxon
    {
        $taxon = new Taxon();
        $taxon->setCurrentLocale('en_US');
        $taxon->setFallbackLocale('en_US');
        $taxon->setName($name);

        return $taxon;
    }

    private function media(string $type, string $path, int $position): TaxonImage
    {
        $media = new TaxonImage();
        $media->setType($type);
        $media->setPath($path);
        $media->setPosition($position);

        return $media;
    }
}
