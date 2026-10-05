<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\Autocomplete;

use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\AdminBundle\Form\Type\TranslatableAutocompleteType;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;

#[AsEntityAutocompleteField(
    alias: 'cyllene_advanced_taxon_child_taxon',
    route: 'sylius_admin_entity_autocomplete',
)]
final class ChildTaxonAutocompleteType extends AbstractType
{
    public function __construct(
        private readonly string $taxonClass,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => $this->taxonClass,
        ]);

        $resolver->setDefault('extra_options', []);
        $resolver->setAllowedTypes('extra_options', 'array');

        $resolver->setDefault('choice_label', 'fullname');

        // Added after the Sylius normalizer, which turns the typed text into a search on the code and
        // the translated name: replacing it would list every candidate whatever the visitor types.
        $resolver->addNormalizer('filter_query', static function (Options $options, ?callable $filterQuery): callable {
            /** @var array<string, mixed> $extraOptions */
            $extraOptions = $options['extra_options'];
            $parentTaxonId = $extraOptions['parent_taxon_id'] ?? null;
            $parentTaxonId = is_numeric($parentTaxonId) ? (int) $parentTaxonId : null;

            return static function (QueryBuilder $queryBuilder, string $query, EntityRepository $repository) use ($filterQuery, $parentTaxonId): void {
                if ($parentTaxonId === null || $parentTaxonId <= 0) {
                    $queryBuilder->andWhere('1 = 0');

                    return;
                }

                if ($filterQuery !== null) {
                    $filterQuery($queryBuilder, $query, $repository);
                }

                $queryBuilder
                    ->andWhere('entity.parent = :currentParentTaxonId')
                    ->setParameter('currentParentTaxonId', $parentTaxonId)
                ;
            };
        });
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'cyllene_advanced_taxon_child_taxon_autocomplete';
    }

    #[\Override]
    public function getParent(): string
    {
        return TranslatableAutocompleteType::class;
    }
}
