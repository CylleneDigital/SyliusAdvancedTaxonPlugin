<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Command;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\ConditionalTaxonSynchronizerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cyllene:advanced-taxon:sync-conditional-taxons',
    description: 'Synchronize products for conditional taxons according to facet conditions while keeping existing positions.',
)]
final readonly class SyncConditionalTaxonsCommand
{
    public function __construct(
        private ConditionalTaxonSynchronizerInterface $assigner,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $summary = $this->assigner->syncAllConditionalTaxons();

        $io->success(sprintf(
            'Conditional taxons synced: %d taxons, %d matched products, %d attached, %d detached.',
            $summary['taxons'],
            $summary['matched'],
            $summary['attached'],
            $summary['detached'],
        ));

        return Command::SUCCESS;
    }
}
