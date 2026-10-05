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
    alias: 'cyllene_advanced_taxon_attached_product',
    route: 'sylius_admin_entity_autocomplete',
)]
final class TaxonAttachedProductAutocompleteType extends AbstractType
{
    public function __construct(
        #[Autowire('%sylius.model.product.class%')]
        private readonly string $productClass,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => $this->productClass,
        ]);

        $resolver->setDefault('extra_options', []);
        $resolver->setAllowedTypes('extra_options', 'array');

        $resolver->setDefault('filter_query', null);
        $resolver->setNormalizer('filter_query', static function (Options $options, ?callable $filterQuery): callable {
            /** @var array<string, mixed> $extraOptions */
            $extraOptions = $options['extra_options'];
            $taxonId = $extraOptions['taxon_id'] ?? null;
            $taxonId = is_numeric($taxonId) ? (int) $taxonId : null;

            return static function (QueryBuilder $queryBuilder, string $query, EntityRepository $repository) use ($filterQuery, $taxonId): void {
                if ($taxonId === null || $taxonId <= 0) {
                    $queryBuilder->andWhere('1 = 0');

                    return;
                }

                if ($filterQuery !== null) {
                    $filterQuery($queryBuilder, $query, $repository);
                }

                $queryBuilder
                    ->innerJoin('entity.productTaxons', 'atpt')
                    ->andWhere('IDENTITY(atpt.taxon) = :currentTaxonId')
                    ->setParameter('currentTaxonId', $taxonId)
                ;
            };
        });
    }

    public function getBlockPrefix(): string
    {
        return 'cyllene_advanced_taxon_attached_product_autocomplete';
    }

    public function getParent(): string
    {
        return TranslatableAutocompleteType::class;
    }
}
