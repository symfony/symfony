<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ImportMap\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\ImportMap\ImportMapManager;
use Symfony\Component\ImportMap\PackageOptions;

/**
 * @experimental
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[AsCommand(name: 'importmap:require', description: 'Requires JavaScript packages')]
final class RequireCommand extends Command
{
    public function __construct(
        protected readonly ImportMapManager $importMapManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The packages to add');
        $this->addOption('download', 'd', InputOption::VALUE_NONE, 'Download packages locally');
        $this->addOption('preload', 'p', InputOption::VALUE_NONE, 'Preload packages');
        $this->addOption('path', 'pa', InputOption::VALUE_REQUIRED, 'Import a package from a local directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $packageList = $input->getArgument('packages');
        if ($input->hasOption('path') && count($packageList) > 1) {
            $io->error('The "--path" option can only be used when you require a single package.');

            return Command::FAILURE;
        }

        $packageOptions = new PackageOptions(
            $input->getOption('download'),
            $input->getOption('preload'),
            $input->getOption('path'),
        );

        $packages = [];
        foreach ($packageList as $packageName) {
            $packages[$packageName] = $packageOptions;
        }

        $this->importMapManager->require($packages);

        return Command::SUCCESS;
    }
}
