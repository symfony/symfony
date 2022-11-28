<?php

declare(strict_types=1);

namespace Symfony\Component\ImportMaps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\ImportMaps\ImportMapManager;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[AsCommand(name: 'importmap:export', description: 'Exports the importmap JSON')]
final class ExportCommand extends Command
{
    public function __construct(
        private readonly ImportMapManager $importMapManager,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->importMapManager->getImportMap());

        return Command::SUCCESS;
    }
}
