<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\TaxonImage as BaseTaxonImage;
use Sylius\Resource\Model\TranslatableInterface;
use Sylius\Resource\Model\TranslatableTrait;
use Sylius\Resource\Model\TranslationInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_taxon_image')]
class TaxonImage extends BaseTaxonImage implements TranslatableInterface
{
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
        getTranslation as private doGetTranslation;
    }

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(name: 'show_card_text', type: 'boolean', options: ['default' => true])]
    private bool $showCardText = true;

    /** @var Collection<string, TaxonImageTranslation> */
    #[ORM\OneToMany(
        mappedBy: 'translatable',
        targetEntity: TaxonImageTranslation::class,
        cascade: ['all'],
        orphanRemoval: true,
        indexBy: 'locale',
    )]
    protected $translations;

    public function __construct()
    {
        $this->initializeTranslationsCollection();
    }

    public function getTranslation(?string $locale = null): TaxonImageTranslation
    {
        /** @var TaxonImageTranslation $translation */
        $translation = $this->doGetTranslation($locale);

        return $translation;
    }

    protected function createTranslation(): TranslationInterface
    {
        return new TaxonImageTranslation();
    }

    public function getTitle(): ?string
    {
        return $this->findTranslation()?->getTitle() ?? $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;

        $this->getTranslationForCurrentLocale()?->setTitle($title);
    }

    public function getTitleForLocale(?string $locale): ?string
    {
        $title = $this->findTranslation($locale)?->getTitle();

        return ($title !== null && $title !== '') ? $title : $this->title;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = self::sanitizeDestinationUrl($url);
    }

    /**
     * Returns the destination URL only when it is safe to use as a link target.
     *
     * Only http(s) URLs and same-origin relative paths are accepted. Anything else
     * ("javascript:", "data:", protocol relative "//host", ...) is dropped, since the value is
     * rendered inside an "href" attribute on the storefront and would otherwise be a stored XSS
     * vector.
     */
    public static function sanitizeDestinationUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        return preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }

    public function getDescription(): ?string
    {
        return $this->findTranslation()?->getDescription() ?? $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;

        $this->getTranslationForCurrentLocale()?->setDescription($description);
    }

    public function getDescriptionForLocale(?string $locale): ?string
    {
        $description = $this->findTranslation($locale)?->getDescription();

        return ($description !== null && $description !== '') ? $description : $this->description;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function isShowCardText(): bool
    {
        return $this->showCardText;
    }

    public function setShowCardText(bool $showCardText): void
    {
        $this->showCardText = $showCardText;
    }

    /**
     * Resolves a translation without creating one, walking the same fallback chain as
     * {@see TranslatableTrait::getTranslation()} but never throwing nor mutating the collection.
     */
    private function findTranslation(?string $locale = null): ?TaxonImageTranslation
    {
        foreach ([$locale, $this->currentLocale, $this->fallbackLocale] as $candidateLocale) {
            if (!is_string($candidateLocale) || $candidateLocale === '') {
                continue;
            }

            $translation = $this->translations->get($candidateLocale);
            if ($translation instanceof TaxonImageTranslation) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * Returns the translation of the current locale, creating it when the locale is known.
     */
    private function getTranslationForCurrentLocale(): ?TaxonImageTranslation
    {
        $locale = $this->currentLocale;

        if (!is_string($locale) || $locale === '') {
            return null;
        }

        return $this->getTranslation($locale);
    }
}
