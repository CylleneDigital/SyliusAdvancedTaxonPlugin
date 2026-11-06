<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Twig\Component\Taxon;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\TaxonTreePresenter;
use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\AdminBundle\Doctrine\Query\Taxon\AllTaxonsInterface;
use Sylius\Bundle\AdminBundle\Twig\Component\Taxon\TreeComponent as SyliusTreeComponent;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;

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
     * The taxon a move has just touched, for the render the move triggers: a folded tree would
     * otherwise hide it again, and its branch would have to be reopened between each click.
     */
    #[LiveProp]
    public ?int $movedTaxonId = null;

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

    #[LiveAction]
    public function moveUp(#[LiveArg] int $taxonId): void
    {
        parent::moveUp($taxonId);

        $this->movedTaxonId = $taxonId;
    }

    #[LiveAction]
    public function moveDown(#[LiveArg] int $taxonId): void
    {
        parent::moveDown($taxonId);

        $this->movedTaxonId = $taxonId;
    }

    #[\Override]
    public function getTree(): array
    {
        return $this->taxonTreePresenter->present(
            parent::getTree(),
            $this->search,
            self::AUTO_OPEN_LIMIT,
            $this->movedTaxonId,
        );
    }

    /**
     * The moved taxon only opens the tree for the response of the move: the request that follows, a
     * search included, goes back to the default folding. Post-hydrate runs before the action, so a
     * move sets the taxon again and keeps its branch open.
     */
    #[PostHydrate]
    public function forgetMovedTaxon(): void
    {
        $this->movedTaxonId = null;
    }
}
