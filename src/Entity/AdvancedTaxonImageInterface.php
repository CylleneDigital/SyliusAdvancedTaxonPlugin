<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

use Sylius\Component\Core\Model\TaxonImageInterface;
use Sylius\Resource\Model\TranslatableInterface;

interface AdvancedTaxonImageInterface extends TaxonImageInterface, TranslatableInterface
{
    public function getTranslation(?string $locale = null): TaxonImageTranslation;

    public function getTitle(): ?string;

    public function setTitle(?string $title): void;

    public function getTitleForLocale(?string $locale): ?string;

    public function getUrl(): ?string;

    public function setUrl(?string $url): void;

    public function getDescription(): ?string;

    public function setDescription(?string $description): void;

    public function getDescriptionForLocale(?string $locale): ?string;

    public function getPosition(): int;

    public function setPosition(int $position): void;

    public function isShowCardText(): bool;

    public function setShowCardText(bool $showCardText): void;
}
