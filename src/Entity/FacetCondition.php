<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Entity;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Repository\FacetConditionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FacetConditionRepository::class)]
#[ORM\Table(name: 'cyllene_advanced_taxon_facet_condition')]
class FacetCondition
{
    public const TYPE_ATTRIBUTE = 'attribute';

    public const TYPE_OPTION = 'option';

    public const TYPE_NAME = 'name';

    public const TYPE_DESCRIPTION = 'description';

    public const TYPE_STOCK = 'stock';

    public const TYPE_TAXON = 'taxon_membership';

    /** Types requiring a referenceCode (attribute code, option code, or taxon code) */
    public const TYPES_WITH_REFERENCE = [self::TYPE_ATTRIBUTE, self::TYPE_OPTION, self::TYPE_TAXON];

    /** Types where the value field is not applicable */
    public const TYPES_WITHOUT_VALUE = [self::TYPE_STOCK];

    /** Operators expressed as a `NOT EXISTS` constraint in product queries. */
    public const NEGATIVE_OPERATORS = ['not_equals', 'not_contains', 'not_in'];

    public const OPERATORS_BY_TYPE = [
        self::TYPE_ATTRIBUTE => ['equals', 'not_equals', 'contains', 'not_contains'],
        self::TYPE_OPTION => ['in', 'not_in'],
        self::TYPE_NAME => ['contains', 'not_contains', 'equals', 'not_equals'],
        self::TYPE_DESCRIPTION => ['contains', 'not_contains'],
        self::TYPE_STOCK => ['is_in_stock'],
        self::TYPE_TAXON => ['in', 'not_in'],
    ];

    public static function isNegativeOperator(string $operator): bool
    {
        return in_array($operator, self::NEGATIVE_OPERATORS, true);
    }

    /**
     * Returns the positive operator matching a negated one.
     *
     * Used to build `NOT EXISTS` subqueries: "value not equals X" becomes
     * "does not exist any value equals X".
     */
    public static function negatedOperator(string $operator): string
    {
        return match ($operator) {
            'not_equals' => 'equals',
            'not_contains' => 'contains',
            'not_in' => 'in',
            default => $operator,
        };
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    /** @phpstan-ignore property.unusedType */
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Taxon::class, inversedBy: 'facetConditions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Taxon $taxon = null;

    #[ORM\Column(length: 32, name: 'condition_type')]
    private string $conditionType = self::TYPE_ATTRIBUTE;

    #[ORM\Column(length: 32)]
    private string $operator = 'equals';

    /** Attribute code, option code or taxon code depending on conditionType */
    #[ORM\Column(length: 255, nullable: true, name: 'reference_code')]
    private ?string $referenceCode = null;

    #[ORM\Column(nullable: true)]
    private ?string $value = null;

    #[ORM\Column(type: 'integer')]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaxon(): ?Taxon
    {
        return $this->taxon;
    }

    public function setTaxon(?Taxon $taxon): void
    {
        $this->taxon = $taxon;
    }

    public function getConditionType(): string
    {
        return $this->conditionType;
    }

    public function setConditionType(string $conditionType): void
    {
        $this->conditionType = $conditionType;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function setOperator(string $operator): void
    {
        $this->operator = $operator;
    }

    public function getReferenceCode(): ?string
    {
        return $this->referenceCode;
    }

    public function setReferenceCode(?string $referenceCode): void
    {
        $this->referenceCode = $referenceCode;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
