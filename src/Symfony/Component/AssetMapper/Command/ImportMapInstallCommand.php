<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Command;

use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Downloads all assets that should be downloaded.
 *
 * @author Jonathan Scheiber <contact@jmsche.fr>
 */
#[AsCommand(name: 'importmap:install', description: 'Downloads all assets that should be downloaded.')]
final class ImportMapInstallCommand extends Command
{
    public function __construct(
        private readonly ImportMapManager $importMapManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $downloadedPackages = $this->importMapManager->downloadMissingPackages();
        $io->success(sprintf('Downloaded %d assets.', \count($downloadedPackages)));

        return Command::SUCCESS;
    }
}
