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
 * @experimental
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[AsCommand(name: 'importmap:update', description: 'Updates all JavaScript packages to their latest versions')]
final class ImportMapUpdateCommand extends Command
{
    public function __construct(
        protected readonly ImportMapManager $importMapManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->importMapManager->update();

        $io->success('Updated all packages in importmap.php.');

        return Command::SUCCESS;
    }
}
