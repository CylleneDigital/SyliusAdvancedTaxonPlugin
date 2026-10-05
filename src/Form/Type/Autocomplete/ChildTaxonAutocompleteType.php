<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Form\Type\Autocomplete;

use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\AdminBundle\Form\Type\TranslatableAutocompleteType;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;

#[AutoconfigureTag('form.type')]
#[AsEntityAutocompleteField(
    alias: 'cyllene_advanced_taxon_child_taxon',
    route: 'sylius_admin_entity_autocomplete',
)]
final class ChildTaxonAutocompleteType extends AbstractType
{
    public function __construct(
        #[Autowire('%sylius.model.taxon.class%')]
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

        $resolver->setDefault('choice_label', static function (Options $options): string {
            /** @var array<string, mixed> $extraOptions */
            $extraOptions = $options['extra_options'];
            $label = $extraOptions['choice_label'] ?? null;

            return is_string($label) && $label !== '' ? $label : 'fullname';
        });

        $resolver->setDefault('filter_query', null);
        $resolver->setNormalizer('filter_query', static function (Options $options, ?callable $filterQuery): callable {
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

    public function getBlockPrefix(): string
    {
        return 'cyllene_advanced_taxon_child_taxon_autocomplete';
    }

    public function getParent(): string
    {
        return TranslatableAutocompleteType::class;
    }
}
