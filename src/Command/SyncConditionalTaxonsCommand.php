<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Command;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Service\ConditionalTaxonSynchronizerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'cyllene:advanced-taxon:sync-conditional-taxons',
    description: 'Synchronize products for conditional taxons according to facet conditions while keeping existing positions.',
)]
final class SyncConditionalTaxonsCommand extends Command
{
    public function __construct(
        private readonly ConditionalTaxonSynchronizerInterface $assigner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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
