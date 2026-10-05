<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\TaxonImageInterface;
use Sylius\Resource\Model\AbstractTranslation;
use Sylius\Resource\Model\TranslatableInterface;

#[ORM\Entity]
#[ORM\Table(name: 'cyllene_advanced_taxon_image_translation')]
#[ORM\UniqueConstraint(name: 'image_translation_uniq', columns: ['translatable_id', 'locale'])]
class TaxonImageTranslation extends AbstractTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /** @phpstan-ignore property.unusedType */
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    protected ?string $locale = null;

    #[ORM\ManyToOne(targetEntity: TaxonImageInterface::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'translatable_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    protected ?TranslatableInterface $translatable = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }
}
