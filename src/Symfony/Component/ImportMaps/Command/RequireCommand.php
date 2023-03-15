<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ImportMaps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\ImportMaps\Env;
use Symfony\Component\ImportMaps\PackageOptions;
use Symfony\Component\ImportMaps\Provider;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[AsCommand(name: 'importmap:require', description: 'Requires JavaScript packages')]
final class RequireCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The packages to add');
        $this->addOption('download', 'd', InputOption::VALUE_NONE, 'Download packages locally');
        $this->addOption('preload', 'p', InputOption::VALUE_NONE, 'Preload packages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packageOptions = new PackageOptions(
            $input->getOption('download'),
            $input->getOption('preload')
        );

        $packages = [];
        foreach ($input->getArgument('packages') as $package) {
            $packages[$package] = $packageOptions;
        }

        $this->importMapManager->require(
            $packages,
            Env::from($input->getOption('js-env')),
            Provider::from($input->getOption('provider')),
        );

        return Command::SUCCESS;
    }
}
