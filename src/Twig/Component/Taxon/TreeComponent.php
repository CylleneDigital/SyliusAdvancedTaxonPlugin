<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Component\Taxon;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonTreePresenter;
use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\AdminBundle\Doctrine\Query\Taxon\AllTaxonsInterface;
use Sylius\Bundle\AdminBundle\Twig\Component\Taxon\TreeComponent as SyliusTreeComponent;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * The Sylius taxon tree, searched and folded on a large catalog.
 *
 * The tree stays the Sylius one: the search field is a live model of this component, so typing
 * re-renders the tree data the Sylius tree controller reads, with neither the controller nor the
 * rows it renders touched.
 */
final class TreeComponent extends SyliusTreeComponent
{
    /**
     * Above this many taxons, the tree is folded down to its roots until a term is searched: a
     * catalog of thousands would otherwise open as an unreadable wall of taxons.
     */
    private const AUTO_OPEN_LIMIT = 100;

    #[LiveProp(writable: true)]
    public string $search = '';

    /**
     * @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository
     */
    public function __construct(
        AllTaxonsInterface $allTaxons,
        TaxonRepositoryInterface $taxonRepository,
        ObjectManager $taxonManager,
        private readonly TaxonTreePresenter $taxonTreePresenter,
    ) {
        parent::__construct($allTaxons, $taxonRepository, $taxonManager);
    }

    #[\Override]
    public function getTree(): array
    {
        return $this->taxonTreePresenter->present(parent::getTree(), $this->search, self::AUTO_OPEN_LIMIT);
    }
}
